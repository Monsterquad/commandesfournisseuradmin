<?php

namespace commandesfournisseuradmin\controllers\front;
use Context;
use ModuleFrontController;

/**
 * @author Sébastien Monterisi <contact@seb7.fr>
 */
class commandesfournisseuradminajaxadminModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (Context::getContext()->employee) {
            throw new \Exception("empliy"); // @todo empliy 
            var_dump(Context::getContext()->employee);
        } else {
            throw new \Exception("not empl"); // @todo not empl 
        }
        throw new \Exception("i"); // @todo i
    }

    public function run()
    {
        $this->postProcess();
    }


//    public function init()
//    {
//        throw new \Exception("inside"); // @todo inside
//    }


}

