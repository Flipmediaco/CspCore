<?php
namespace Flipmediaco\CspCore\Plugin;

use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;

class CspOptimizerPlugin
{
    const CSP_REMOVAL_LIST_XML = 'csp_removelist.xml';

    private LoggerInterface $logger;
    private ModuleDirReader $moduleDirReader;
    private IoFile $ioFile;
    private State $appState;

    private ?array $removeList = null;

    public function __construct(
        ModuleDirReader $moduleDirReader,
        IoFile $ioFile,
        LoggerInterface $logger,
        State $appState
    ) {
        $this->moduleDirReader = $moduleDirReader;
        $this->ioFile = $ioFile;
        $this->logger = $logger;
        $this->appState = $appState;
    }

    public function afterCollect($subject, array $result): array
    {
        $this->log('afterCollect called - result count: ' . count($result));

        foreach ($result as $policyIndex => $policy) {
            if (!$policy instanceof FetchPolicy) {
                $this->log('Skipping non-FetchPolicy at index: ' . $policyIndex);
                continue;
            }

            $policyName = $policy->getId();
            $this->log('Processing policy: ' . $policyName . ' (index: ' . $policyIndex . ')');

            $originalHostSources = $policy->getHostSources();
            $this->log('Original hostSources count: ' . count($originalHostSources));

            $sourcesAfterRemoval = $this->actionRemoveList($policyName, $originalHostSources);
            $cleanedHostSources = $this->deduplicateAndNormalize($sourcesAfterRemoval);

            $newPolicy = new FetchPolicy(
                $policy->getId(),
                $policy->isNoneAllowed(),
                $cleanedHostSources,
                $policy->getSchemeSources(),
                $policy->isSelfAllowed(),
                $policy->isInlineAllowed(),
                $policy->isEvalAllowed(),
                $policy->getNonceValues(),
                $policy->getHashes(),
                $policy->isDynamicAllowed(),
                $policy->areEventHandlersAllowed()
            );

            $this->log('Cleaned hostSources count: ' . count($cleanedHostSources));
            $result[$policyIndex] = $newPolicy;
        }

        return $result;
    }

    private function actionRemoveList(string $policyName, array $sources): array
    {
        if ($this->removeList === null) {
            $this->removeList = $this->getRemovelist();
        }

        if (empty($this->removeList[$policyName])) {
            $this->log('No removals configured for: ' . $policyName);
            return $sources;
        }

        $removalEntries = $this->removeList[$policyName];
        $this->log('Applying removal list for ' . $policyName . ' - ' . count($removalEntries) . ' entries');

        return array_filter($sources, function ($source) use ($removalEntries, $policyName) {
            if (in_array($source, $removalEntries, true)) {
                $this->log('Dropping source: ' . $source);
                return false;
            }
            return true;
        });
    }

    private function deduplicateAndNormalize(array $sources): array
    {
        $normalized = [];
        $wildcards = [];

        foreach ($sources as $source) {
            $cleaned = $this->normalizeSource($source);
            if (str_starts_with($cleaned, '*.')) {
                $wildcards[$cleaned] = $cleaned;
                $normalized[$cleaned] = $cleaned;
            }
        }

        foreach ($sources as $source) {
            $cleaned = $this->normalizeSource($source);
            if (!str_starts_with($cleaned, '*.')) {
                $domain = $this->stripSubdomain($cleaned);
                if (!isset($wildcards['*.' . $domain])) {
                    $normalized[$cleaned] = $source;
                } else {
                    $this->log('Stripping specific domain ' . $source . ' (covered by wildcard *.' . $domain . ')');
                }
            }
        }

        return array_values($normalized);
    }

    private function getRemovelist(): array
    {
        $path = $this->moduleDirReader->getModuleDir('etc', 'Flipmediaco_CspProject') . '/' . self::CSP_REMOVAL_LIST_XML;

        if (!$this->ioFile->fileExists($path)) {
            $this->log('No CSP removal file found at ' . $path);
            return [];
        }

        $content = $this->ioFile->read($path);
        $xml = simplexml_load_string($content);

        $removalList = [];

        if (isset($xml->policies->policy)) {
            foreach ($xml->policies->policy as $policy) {
                $policyId = (string) $policy['id'];
                foreach ($policy->values->value as $value) {
                    $removalList[$policyId][] = (string) $value;
                }
            }
        }

        return $removalList;
    }

    private function normalizeSource(string $source): string
    {
        $source = strtolower(trim($source));
        if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $source)) {
            $source = 'http://' . $source;
        }
        $host = parse_url($source, PHP_URL_HOST) ?? $source;
        return strtolower($host);
    }

    private function stripSubdomain(string $url): string
    {
        $host = parse_url(trim($url), PHP_URL_HOST) ?: $url;
        $parts = explode('.', strtolower($host));
        $count = count($parts);

        if ($count < 2) return $host;

        $tld = $parts[$count - 1];
        $second = $parts[$count - 2];

        if (strlen($tld) > 3 || strlen($second) > 3 || $count < 3) {
            return $second . '.' . $tld;
        }

        return ($parts[$count - 3] ?? '') . '.' . $second . '.' . $tld;
    }

    private function log(string $message): void
    {
        try {
            if ($this->appState->getMode() === State::MODE_DEVELOPER) {
                $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/flipmediaco_csp.log');
                $logger = new \Zend_Log();
                $logger->addWriter($writer);
                $logger->info('[Flipmediaco_CSP] ' . $message);
            }
        } catch (\Exception $e) {
            // Do not throw errors if logging fails
        }
    }
}