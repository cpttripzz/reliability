<?php

namespace NatInt\Controllers;

class AuthController extends ControllerBase
{

    public function indexAction()
    {
        if ($this->request->isPost()) {
            $user =  $this->di->get('authService')->getUserByCredentials($this->request);
            if ($user !== false) {
                $this->session->set('auth', array(
                    'id'    => $user->id,
                    'username'  => $user->username
                ));
                $this->flash->success('Welcome ' . $user->username);

                return $this->response->redirect(
                    array(
                        "controller" => "user-review-report",
                        "action" => "index"
                    )
                );
            }

            $this->flash->error('Wrong email/password');
        }

    }

    public function logoutAction()
    {
        $this->session->destroy();
    }
}

