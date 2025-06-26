<?php

declare(strict_types=1);

namespace Bgustyp\LaravelZmq\Support;

use ZMQ;

/**
 * LibZMQ 4.3.4 Compatibility Helper
 */
class LibZmqCompatibility
{
    private static ?array $versionInfo = null;
    private static ?array $capabilities = null;

    /**
     * Get libzmq version information
     */
    public static function getVersionInfo(): array
    {
        if (self::$versionInfo === null) {
            self::$versionInfo = [
                'php_zmq_version' => phpversion('zmq') ?: 'unknown',
                'libzmq_version' => self::getLibZmqVersion(),
                'zmq_constants' => self::getAvailableConstants(),
                'supported_features' => self::getSupportedFeatures(),
            ];
        }

        return self::$versionInfo;
    }

    /**
     * Check if libzmq version is 4.3.4 or higher
     */
    public static function isLibZmq434OrHigher(): bool
    {
        $version = self::getLibZmqVersion();
        if (!$version) return false;

        return version_compare($version, '4.3.4', '>=');
    }

    /**
     * Get supported socket options for libzmq 4.3.4
     */
    public static function getSupportedSocketOptions(): array
    {
        $options = [
            // Basic options (available in all versions)
            'ZMQ::SOCKOPT_LINGER' => ZMQ::SOCKOPT_LINGER,
            'ZMQ::SOCKOPT_IDENTITY' => ZMQ::SOCKOPT_IDENTITY,
            'ZMQ::SOCKOPT_SNDHWM' => ZMQ::SOCKOPT_SNDHWM,
            'ZMQ::SOCKOPT_RCVHWM' => ZMQ::SOCKOPT_RCVHWM,
            'ZMQ::SOCKOPT_SNDTIMEO' => ZMQ::SOCKOPT_SNDTIMEO,
            'ZMQ::SOCKOPT_RCVTIMEO' => ZMQ::SOCKOPT_RCVTIMEO,
        ];

        // libzmq 4.3.4+ specific options
        if (self::isLibZmq434OrHigher()) {
            // CURVE security (requires libsodium)
            if (defined('ZMQ::SOCKOPT_CURVE_SERVER')) {
                $options['ZMQ::SOCKOPT_CURVE_SERVER'] = ZMQ::SOCKOPT_CURVE_SERVER;
                $options['ZMQ::SOCKOPT_CURVE_PUBLICKEY'] = ZMQ::SOCKOPT_CURVE_PUBLICKEY;
                $options['ZMQ::SOCKOPT_CURVE_SECRETKEY'] = ZMQ::SOCKOPT_CURVE_SECRETKEY;
                $options['ZMQ::SOCKOPT_CURVE_SERVERKEY'] = ZMQ::SOCKOPT_CURVE_SERVERKEY;
            }

            // Heartbeat options
            if (defined('ZMQ::SOCKOPT_HEARTBEAT_IVL')) {
                $options['ZMQ::SOCKOPT_HEARTBEAT_IVL'] = ZMQ::SOCKOPT_HEARTBEAT_IVL;
                $options['ZMQ::SOCKOPT_HEARTBEAT_TTL'] = ZMQ::SOCKOPT_HEARTBEAT_TTL;
                $options['ZMQ::SOCKOPT_HEARTBEAT_TIMEOUT'] = ZMQ::SOCKOPT_HEARTBEAT_TIMEOUT;
            }

            // Connection timeout
            if (defined('ZMQ::SOCKOPT_CONNECT_TIMEOUT')) {
                $options['ZMQ::SOCKOPT_CONNECT_TIMEOUT'] = ZMQ::SOCKOPT_CONNECT_TIMEOUT;
            }

            // ZAP domain for security
            if (defined('ZMQ::SOCKOPT_ZAP_DOMAIN')) {
                $options['ZMQ::SOCKOPT_ZAP_DOMAIN'] = ZMQ::SOCKOPT_ZAP_DOMAIN;
            }
        }

        return $options;
    }

    /**
     * Check if CURVE security is available
     */
    public static function isCurveSecurityAvailable(): bool
    {
        return self::isLibZmq434OrHigher() &&
            extension_loaded('sodium') &&
            defined('ZMQ::SOCKOPT_CURVE_SERVER');
    }

    /**
     * Get capabilities for current libzmq version
     */
    public static function getCapabilities(): array
    {
        if (self::$capabilities === null) {
            self::$capabilities = [
                'curve_security' => self::isCurveSecurityAvailable(),
                'heartbeat' => defined('ZMQ::SOCKOPT_HEARTBEAT_IVL'),
                'connection_timeout' => defined('ZMQ::SOCKOPT_CONNECT_TIMEOUT'),
                'zap_authentication' => defined('ZMQ::SOCKOPT_ZAP_DOMAIN'),
                'draft_api' => self::isDraftApiAvailable(),
                'radio_dish' => self::isRadioDishAvailable(),
            ];
        }

        return self::$capabilities;
    }

    /**
     * Validate configuration for libzmq 4.3.4
     */
    public static function validateConfiguration(array $config): array
    {
        $errors = [];
        $warnings = [];

        // Check libzmq version
        if (!self::isLibZmq434OrHigher()) {
            $warnings[] = 'LibZMQ version is older than 4.3.4. Some features may not be available.';
        }

        // Check CURVE security configuration
        if (isset($config['security']['curve']['enabled']) && $config['security']['curve']['enabled']) {
            if (!self::isCurveSecurityAvailable()) {
                $errors[] = 'CURVE security is enabled but not available. Requires libzmq 4.3.4+ and sodium extension.';
            }

            $requiredKeys = ['server_key', 'public_key', 'secret_key'];
            foreach ($requiredKeys as $key) {
                if (empty($config['security']['curve'][$key])) {
                    $errors[] = "CURVE security requires '{$key}' to be configured.";
                }
            }
        }

        // Check heartbeat configuration
        if (isset($config['heartbeat']['enabled']) && $config['heartbeat']['enabled']) {
            if (!defined('ZMQ::SOCKOPT_HEARTBEAT_IVL')) {
                $warnings[] = 'Heartbeat is enabled but not supported in this libzmq version.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private static function getLibZmqVersion(): ?string
    {
        // Try to get version from zmq_version() function if available
        if (function_exists('zmq_version')) {
            return zmq_version();
        }

        // Try to get from ZMQ class constants
        if (defined('ZMQ::LIBZMQ_VER')) {
            return ZMQ::LIBZMQ_VER;
        }

        // Fallback: check for version-specific constants
        if (defined('ZMQ::SOCKOPT_HEARTBEAT_IVL')) {
            return '4.3.4+'; // Heartbeat was added in 4.3.4
        }

        if (defined('ZMQ::SOCKOPT_CURVE_SERVER')) {
            return '4.0.0+'; // CURVE was added in 4.0
        }

        return null;
    }

    private static function getAvailableConstants(): array
    {
        $reflection = new \ReflectionClass('ZMQ');
        return array_keys($reflection->getConstants());
    }

    private static function getSupportedFeatures(): array
    {
        $features = [];

        // Check for various features by constant availability
        $featureConstants = [
            'CURVE Security' => 'ZMQ::SOCKOPT_CURVE_SERVER',
            'Heartbeat' => 'ZMQ::SOCKOPT_HEARTBEAT_IVL',
            'Connection Timeout' => 'ZMQ::SOCKOPT_CONNECT_TIMEOUT',
            'ZAP Authentication' => 'ZMQ::SOCKOPT_ZAP_DOMAIN',
            'Monitoring' => 'ZMQ::EVENT_ALL',
            'DRAFT API' => 'ZMQ::SOCKET_RADIO',
        ];

        foreach ($featureConstants as $feature => $constant) {
            $features[$feature] = defined($constant);
        }

        return $features;
    }

    private static function isDraftApiAvailable(): bool
    {
        return defined('ZMQ::SOCKET_RADIO') && defined('ZMQ::SOCKET_DISH');
    }

    private static function isRadioDishAvailable(): bool
    {
        return defined('ZMQ::SOCKET_RADIO') && defined('ZMQ::SOCKET_DISH');
    }
}
