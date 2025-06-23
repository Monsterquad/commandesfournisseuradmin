<?php

function upgrade_module_0_4_1(Module $module)
{
    return $module->registerHook('actionMailAlterMessageBeforeSend');
}
