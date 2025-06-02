<?php
namespace Flipmediaco\CspCore\Helper;

use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Csp\Model\Policy\FetchPolicyFactory;
use Magento\Framework\Config\Reader\Filesystem;

class CspPolicyMapBuilder
{
    private Filesystem $reader;
    private FetchPolicyFactory $fetchPolicyFactory;
    private array $policyMap = [];

    public function __construct(
        Filesystem $reader,
        FetchPolicyFactory $fetchPolicyFactory
    ) {
        $this->reader = $reader;
        $this->fetchPolicyFactory = $fetchPolicyFactory;
    }

    /**
     * Build policy name → FetchPolicy object map.
     *
     * @return array<string, FetchPolicy>
     */
    public function buildPolicyMap(): array
    {
        if (!empty($this->policyMap)) {
            return $this->policyMap;
        }

        try {
            $config = $this->reader->read();
        } catch (\Throwable $e) {
            // Catch empty XML merge errors gracefully
            return [];
        }

        if (empty($config['policies'])) {
            return [];
        }

        foreach ($config['policies'] as $policyName => $policyData) {
            $sources = [];
            $self = false;

            foreach ($policyData['values'] ?? [] as $value) {
                $valueId = $value['id'];
                if ($valueId === "'self'") {
                    $self = true;
                } else {
                    $sources[] = $valueId;
                }
            }

            $this->policyMap[$policyName] = $this->fetchPolicyFactory->create([
                'sources' => $sources,
                'self' => $self
            ]);
        }

        return $this->policyMap;
    }

    /**
     * Resolve the name of a given FetchPolicy.
     *
     * @param FetchPolicy $target
     * @return string|null
     */
    public function resolvePolicyName(FetchPolicy $target): ?string
    {
        foreach ($this->buildPolicyMap() as $name => $policy) {
            if ($policy == $target) {
                return $name;
            }
        }

        return null;
    }
}