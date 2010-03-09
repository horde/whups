<?php
/**
 * This file contains all Horde_UI_VarRenderer extensions for Whups specific
 * form fields.
 *
 * Copyright 2008-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Horde_UI
 */

/**
 * The Horde_UI_VarRenderer_whups class provides additional methods for
 * rendering Horde_Form_Type_whups_email fields.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Horde_UI
 */
class Horde_Ui_VarRenderer_whups extends Horde_Ui_VarRenderer_Html {

    function _renderVarInput_whups_email($form, &$var, &$vars)
    {
        $name = $var->getVarName();

        $imple = Horde_Ajax_Imple::factory(array('whups', 'ContactAutoCompleter'), array('triggerId' => $name));
        $imple->attach();
        return sprintf('<input type="text" name="%s" id="%s" value="%s" autocomplete="off"%s />',
                       $name,
                       $name,
                       @htmlspecialchars($var->getValue($vars)),
                       $this->_getActionScripts($form, $var))
            . '<span id="' . $name . '_loading_img" style="display:none;">'
            . Horde::img('loading.gif', _("Loading..."))
            . '</span>';
    }

}
