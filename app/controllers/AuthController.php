<?php

namespace NatInt\Controllers;
use NatInt\Services\AuthService;
use NatInt\Models\Users;
class AuthController extends \Phalcon\Mvc\Controller
{
    public function indexAction()
    {
        if ($this->request->isPost()) {

            $email = $this->request->getPost('email');
            $password = $this->request->getPost('password');

            $user = Users::findFirst(array(
                "(email = :email: OR username = :email:) AND password = :password: AND active = 'Y'",
                'bind' => array('email' => $email, 'password' => sha1($password))
            ));
            if ($user != false) {
                $this->_registerSession($user);
                $this->flash->success('Welcome ' . $user->username);
//            return $this->forward('invoices/index');
            }

            $this->flash->error('Wrong email/password');
        }
    }
}

