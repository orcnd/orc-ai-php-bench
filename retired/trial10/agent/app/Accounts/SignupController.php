<?php
declare(strict_types=1);
namespace App\Accounts;

use App\Kernel;

/** POST /signup */
final class SignupController
{
    private Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @param array{email?: string, name?: string} $input
     * @return array{status: int, errors: array<string, string>}
     */
    public function register(array $input): array
    {
        $email = trim($input['email'] ?? '');
        $name = trim($input['name'] ?? '');
        if ($name === '') {
            return ['status' => 422, 'errors' => ['name' => 'required']];
        }
        // TODO #125: validate the e-mail address.
        if ($this->kernel->users->exists($email)) {
            return ['status' => 409, 'errors' => ['email' => 'taken']];
        }
        $this->kernel->users->add($email, $name);
        return ['status' => 201, 'errors' => []];
    }
}
