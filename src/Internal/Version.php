<?php

declare(strict_types=1);

namespace Danipa\Sdk\Internal;

use Composer\InstalledVersions;

/**
 * The installed version of `danipa/sdk`, read from Composer's runtime
 * metadata (`composer-runtime-api ^2`) rather than hard-coded, so the
 * User-Agent can never drift from the tag Packagist served.
 *
 * @internal
 */
final class Version
{
    public const PACKAGE = 'danipa/sdk';

    private static ?string $cached = null;

    private function __construct()
    {
    }

    public static function sdk(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }
        $version = null;
        try {
            if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled(self::PACKAGE)) {
                $version = InstalledVersions::getPrettyVersion(self::PACKAGE);
            }
        } catch (\Throwable) {
            $version = null;
        }
        return self::$cached = ($version === null || $version === '') ? 'unknown' : ltrim($version, 'v');
    }

    public static function userAgent(): string
    {
        return sprintf('danipa-php/%s php/%s', self::sdk(), PHP_VERSION);
    }
}
