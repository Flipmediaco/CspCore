# Flipmediaco_CspCore

A Magento 2 developer tool designed to reduce Content-Security-Policy (CSP) header size by optimising and deduplicating policy values. This module is especially useful when CSP headers exceed the default 8KB Apache header limit, often due to third-party modules or excessive source additions.

---

## 🔧 Features

- Deduplicates host sources per directive
- Normalises source values by removing schemes and trailing slashes
- Wildcard optimisations: removes subdomains when a `*.` wildcard is present
- Retains "naked" (root) domains even when wildcards exist (e.g. keeps `stripe.com` alongside `*.stripe.com`)
- Optional support for a `csp_removelist.xml` to explicitly remove sources
- Compatible with `report-only` and enforced CSP headers

---

## 🧠 How It Works

The plugin intercepts the result of `Magento\Csp\Model\Collector\PolicyCollector::collect()` and inspects each `FetchPolicy` directive.

### The process:
1. Host sources are normalised using `parse_url`, lowercased, and stripped of protocol/scheme and trailing slashes.
2. A deduplication pass removes repeated sources.
3. If a wildcard domain (`*.example.com`) exists, matching subdomains are removed (e.g. `cdn.example.com`).
4. Naked domains (`example.com`) are preserved even if the wildcard exists.
5. Optionally, sources listed in a project-defined `csp_removelist.xml` are removed before normalisation/deduplication.

---

## 📦 Installation

Install as part of a meta-package or manually include in your Magento 2 codebase under `app/code/Flipmediaco/CspCore`.

Register module:

```bash
bin/magento setup:upgrade
bin/magento cache:flush
```

Or Via Composer:

```bash
composer config repositories.flipmediaco-cspcore git https://github.com/Flipmediaco/CspCore.git
composer require flipmediaco/cspcore
bin/magento module:enable Flipmediaco_CspCore
bin/magento setup:upgrade
```

---

## 🧰 Flipmediaco_CspProject

`Flipmediaco_CspProject` is an optional, overrideable module included as a tarball (`Flipmediaco_CspProject.tar`) for defining custom removelist entries without editing `CspCore`.

### Purpose

This module allows site-specific policy reductions by shipping your own `csp_removelist.xml`.

### Features

- Declares a dependency on `Flipmediaco_CspCore`
- Loads its own `etc/csp_removelist.xml` if present
- Can be dropped into `app/code/Flipmediaco/CspProject/` and used as a sandbox for local adjustments

### Blank Version

A blank stub is included for convenience:
```
Flipmediaco_CspProject.tar
├── registration.php
├── etc
│   └── csp_removelist.xml
├── composer.json
├── module.xml
```

---

## 📄 Optional: CSP Removal List (`csp_removelist.xml`)

You can define a list of CSP sources to forcibly remove before deduplication by including a `csp_removelist.xml` file in your override module.

### Path
```
app/code/Flipmediaco/CspProject/etc/csp_removelist.xml
```

### Example
```xml
<?xml version="1.0"?>
<csp_removelist xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Csp:etc/csp_whitelist.xsd">
    <policies>
        <policy id="script-src">
            <values>
                <value id="*.newrelic.com" type="host">*.newrelic.com</value>
            </values>
        </policy>
        <policy id="connect-src">
            <values>
                <value id="*.newrelic.com" type="host">*.newrelic.com</value>
            </values>
        </policy>
        <policy id="img-src">
            <values>
                <value id="*.behance.net" type="host">*.behance.net</value>
            </values>
        </policy>
    </policies>
</csp_removelist>
```

---

## If using CspProject:

```
tar -xf vendor/Flipmediaco/cspcore/Flipmediaco_CspProject.tar -C app/code/Flipmediaco
bin/magento module:enable Flipmediaco_CspProject
bin/magento setup:upgrade
```

---

## 🧪 Debugging

This modules logs to /var/log/flipmediaco_csp.log when site is in developer mode.

Magento will log lines like:

```
[Flipmediaco_CSP] Removed subdomain cdn.stripe.com as wildcard *.stripe.com exists
[Flipmediaco_CSP] Deduplicated script-src → original: 42, cleaned: 29
```

---

## 🧱 Compatibility

- Magento 2.4.6+
- PHP 8.1 / 8.2 / 8.3
- Compatible with Apache and Nginx header constraints

---

## © Licensing

MIT License — Free to use, modify, and distribute with attribution.
```