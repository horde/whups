<?php

namespace Horde\Whups\Config;

use Horde_Form;
use Horde_Config_Form;
use Horde;

class Form extends Horde_Config_Form
{
    public function __construct($vars, $app = 'whups')
    {
        // Add whups specific variables first
        // Consume the legacy 7config xml next
        parent::__construct($vars, $app);
    }
}
