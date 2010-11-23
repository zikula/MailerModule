<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 * @package Zikula
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * Plugin definition class.
 */
class ModulePlugin_SysInfo_Example_Plugin extends Zikula_Plugin implements Zikula_Plugin_Configurable
{
    /**
     * Gettext enabled flag.
     *
     * @var boolean
     */
    protected $gettextEnabled = true;

    /**
     * Setup handler definitions.
     *
     * @return void
     */
    protected function setupHandlerDefinitions()
    {
        $this->addHandlerDefinition('module_dispatch.postexecute', 'addLinks');
        $this->addHandlerDefinition('controller.method_not_found', 'anotherfunction');
    }

    /**
     * Provide plugin meta data.
     *
     * @return array Meta data.
     */
    protected function getMeta()
    {
        return array('displayname' => $this->__('Example SysInfo Plugin'),
                     'description' => $this->__('Adds link to administration menu.'),
                     'version'     => '1.0.0'
                    );
    }

    /**
     * Event handler here.
     *
     * @param Zikula_Event $event Handler.
     *
     * @return void
     */
    public function addLinks(Zikula_Event $event)
    {
        // check if this is for this handler
        if (!($event->getSubject() instanceof SysInfo_Api_Admin && $event['modfunc'][1] == 'getlinks')) {
            return;
        }

        if (SecurityUtil::checkPermission('SysInfo::', '::', ACCESS_ADMIN)) {
            $event->data[] = array('url' => ModUtil::url('SysInfo', 'admin', 'anotherfunction'), 'text' => $this->__('Here is another link'));
        }
    }

    /**
     * Add 'anotherfunction' Event handler .
     *
     * @param Zikula_Event $event Handler.
     *
     * @return void
     */
    public function anotherfunction(Zikula_Event $event)
    {
        // check if this is for this handler
        $subject = $event->getSubject();
        if (!($event['method'] == 'anotherfunction' && $subject instanceof SysInfo_Controller_Admin)) {
            return;
        }

        if (!SecurityUtil::checkPermission('SysInfo::', '::', ACCESS_ADMIN)) {
            return LogUtil::registerPermissionError();
        }

        $view = Zikula_View_plugin::getModulePluginInstance($this->moduleName, $this->pluginName);

        $event->setData($view->fetch('anotherfunction.tpl'));
        $event->setNotified();
    }

    /**
     * Controller configuration getter.
     *
     * @return ModulePlugin_SysInfo_Example_Controller
     */
    public function getConfigurationController()
    {
        return new ModulePlugin_SysInfo_Example_Controller($this->serviceManager, array('plugin' => $this));
    }
}
