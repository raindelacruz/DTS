<?php

require_once '../app/models/User.php';

class Auth extends Controller {

    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    // 👇 ADD THIS
    public function index()
    {
        $this->login();
    }

    public function login() {

        if($_SERVER['REQUEST_METHOD'] == 'POST') {

            $id_number = trim($_POST['id_number']);
            $password  = trim($_POST['password']);

            $user = $this->userModel->login($id_number, $password);

            if($user) {

                $_SESSION['user_id'] = $user->id;
                $_SESSION['department_id'] = $user->department_id;
                $_SESSION['role'] = $user->role;
                $_SESSION['fullname'] = $user->firstname . ' ' . $user->lastname;

                header("Location: /DTS/public/dashboard");
                exit;

            } else {
                die("Invalid Credentials");
            }

        } else {
            $this->view('auth/login');
        }
    }

    public function logout() {
        session_destroy();
        header("Location: /DTS/public/");
        exit;
    }
}
