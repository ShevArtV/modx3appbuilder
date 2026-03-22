<?php

namespace ComponentBuilder;

use MODX\Revolution\modX;
use MODX\Revolution\Transport\modPackageBuilder;

class HeadlessPackageBuilder extends modPackageBuilder
{
    public function __construct(modX &$modx)
    {
        $this->modx = &$modx;
        $this->directory = MODX_CORE_PATH . 'packages/';

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }

        $this->autoselects = [];
    }
}
