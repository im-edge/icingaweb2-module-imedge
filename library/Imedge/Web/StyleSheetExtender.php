<?php

namespace Icinga\Module\Imedge\Web;

use Icinga\Application\Icinga;
use Icinga\Web\StyleSheet;

use function str_repeat;
use function substr_count;

final class StyleSheetExtender extends StyleSheet
{
    protected static bool $imedgeInitializedStyles = false;

    public static function extendIcingaWeb(string $moduleBaseDir): bool
    {
        if (
            !isset($_SERVER['REQUEST_URI'])
            || !preg_match('#/css/icinga(?:\.min)?\.css#', $_SERVER['REQUEST_URI'])
            || self::$imedgeInitializedStyles
        ) {
            return false;
        }

        $pubPath = Icinga::app()->getBaseDir('public');
        $slashes = substr_count($pubPath, '/');
        $prefix = str_repeat('/..', $slashes) . $moduleBaseDir;
        new class ([
            // TODO: module for leaflet?
            // "$prefix/public/css/leaflet.less",
            // "$prefix/public/css/43-leaflet.less",
            // TODO: module for flag-icons
            // "$prefix/public/css/vendor/flag-icons.css",
            "$prefix/public/css/combined.less",
        ]) extends StyleSheet {
            /**
             * @param string[] $files
             * @noinspection PhpMissingParentConstructorInspection
             */
            public function __construct(array $files)
            {
                foreach ($files as $file) {
                    self::$lessFiles[] = $file;
                }
            }
        };
        self::$imedgeInitializedStyles = true;

        return true;
    }
}
