<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CreateSuperAdmin extends BaseCommand
{
    protected $group = 'Auth';
    protected $name = 'superadmin:create';
    protected $description = 'Create a central Super Admin account.';
    protected $usage = 'superadmin:create';

    public function run(array $params)
    {
        $name = trim(CLI::prompt('Super Admin name', null, 'required|max_length[120]'));
        $email = strtolower(trim(CLI::prompt('Super Admin email', null, 'required|valid_email|max_length[190]')));
        $password = CLI::prompt('Password (input may be visible)', null, 'required|min_length[12]|max_length[128]');
        $confirmation = CLI::prompt('Confirm password', null, 'required|min_length[12]|max_length[128]');

        if ($password !== $confirmation) {
            CLI::error('Passwords do not match.');
            return EXIT_ERROR;
        }

        $db = db_connect();
        if ($db->table('super_admins')->where('email', $email)->countAllResults() > 0) {
            CLI::error('A Super Admin with that email already exists.');
            return EXIT_ERROR;
        }

        $created = $db->table('super_admins')->insert([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$created) {
            CLI::error('Unable to create the Super Admin account.');
            return EXIT_ERROR;
        }

        CLI::write('Super Admin account created. Sign in at /super-admin/login.');
        return EXIT_SUCCESS;
    }
}