<?php

namespace App\DTO;

final readonly class AdminUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public bool $isAdmin,
        public ?string $password = null,
    ) {}

    /**
     * @param  array{name: string, email: string, password?: string|null, is_admin?: bool}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            isAdmin: (bool) ($data['is_admin'] ?? false),
            password: $data['password'] ?? null,
        );
    }

    /**
     * @return array{name: string, email: string, password?: string, is_admin: bool}
     */
    public function toArray(bool $includeEmptyPassword = false): array
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => $this->isAdmin,
        ];

        if ($this->password !== null && ($includeEmptyPassword || $this->password !== '')) {
            $data['password'] = $this->password;
        }

        return $data;
    }
}
