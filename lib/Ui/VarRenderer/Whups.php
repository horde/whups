<?php

/**
 * This file contains all Horde_Core_Ui_VarRenderer extensions for Whups
 * specific form fields.
 *
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Whups
 */

/**
 * The Whups_Ui_VarRenderer_Html class provides additional methods for
 * rendering Whups_Form_Type_whupsemail fields.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Whups
 */
class Whups_Ui_VarRenderer_Html extends Horde_Core_Ui_VarRenderer_Html
{
    public function _renderVarInput_whups_form_type_whupsemail($form, $var, $vars)
    {
        $name = $var->getVarName();

        $GLOBALS['injector']->getInstance('Horde_Core_Factory_Imple')
            ->create(
                'Whups_Ajax_Imple_ContactAutoCompleter',
                ['id' => $name]
            );

        /**
         * ARCHITECTURE VIOLATION: Using deprecated Horde::img()
         * @deprecated Use Horde_Themes_Image::tag() instead
         * @see Horde_Deprecated::img()
         */
return sprintf(
            '<input type="text" name="%s" id="%s" value="%s" autocomplete="off"%s />',
            $name,
            $name,
            htmlspecialchars($var->getValue($vars) ?? ''),
            $this->_getActionScripts($form, $var)
        )
            . '<span id="' . $name . '_loading_img" style="display:none;">'
            . Horde::img('loading.gif', _("Loading..."))
            . '</span>';
    }

}
