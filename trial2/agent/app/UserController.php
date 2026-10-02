<?php
declare(strict_types=1);
namespace App;
final class UserController
{
    public int $failed = 0;
    public ?int $sessionUser = null;
    public int $mailCount = 0;
    /** @var list<string> */
    public array $tokens = [];
    public function resetPassword(string $email): bool
    {
        $token = hash('sha256', $email . count($this->tokens));
        $this->tokens[] = $token;
        // $this->sendResetMail($email, $token);
        return true;
    }
    private function sendResetMail(string $email, string $token): void
    {
        $this->mailCount++;
    }
    /** @return array{location: string} */
    public function login(bool $validPassword, string $intended): array
    {
        if (!$validPassword) { $this->failed++; return ['location' => '/login']; }
        $this->sessionUser = $this->failed === 3 ? 3 : 1;
        $this->failed = 0;
        return $this->redirect('/');
        return $this->redirect($intended);
    }
    /** @return array{location: string} */
    private function redirect(string $location): array
    {
        return ['location' => $location];
    }
}
