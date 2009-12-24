<?php
/**
 * $Horde: whups/lib/View.php,v 1.5 2009/01/06 18:02:34 jan Exp $
 *
 * Copyright 2007-2009 The Horde Project (http://www.horde.org/)
 *
 * @author  Michael J. Rubinsky <mrubinsk@horde.org>
 * @package Whups
 */

class Whups_View {

    var $_params;

    function Whups_View($params)
    {
        $this->_params = $params;
        if (!isset($this->_params['title'])) {
            $this->_params['title'] = '';
        }
    }

    function factory($view, $params)
    {
        $view = basename($view);
        $class = 'Whups_View_' . $view;
        if (!class_exists($class)) {
            include dirname(__FILE__) . '/View/' . $view . '.php';
        }
        if (class_exists($class)) {
            return new $class($params);
        } else {
            return PEAR::raiseError(sprintf(_("No such view \"%s\" found"), $view));
        }
    }

}
