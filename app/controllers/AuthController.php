<?php

namespace NatInt\Controllers;
use NatInt\Services\AuthService;
class AuthController extends \Phalcon\Mvc\Controller
{
    public function indexAction()
    {
        if ($this->request->isPost()) {

        $email      = $this->request->getPost('email');
        $password   = $this->request->getPost('password');


        if ($user != false) {
            $this->_registerSession($user);
            $this->flash->success('Welcome ' . $user->name);
//            return $this->forward('invoices/index');
        }

        $this->flash->error('Wrong email/password');
    }
//        return $this->forward('login/index');

    }

}

