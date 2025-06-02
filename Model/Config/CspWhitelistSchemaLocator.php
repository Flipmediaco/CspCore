<?php

namespace Flipmediaco\CspCore\Model\Config;

use Magento\Framework\Config\SchemaLocatorInterface;

class CspWhitelistSchemaLocator implements SchemaLocatorInterface
{
    public function getSchema(): string
    {
        return 'urn:magento:module:Magento_Csp:etc/csp_whitelist.xsd';
    }

    public function getPerFileSchema(): string
    {
        return $this->getSchema();
    }
}