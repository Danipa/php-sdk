<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class Payer
{
    public function __construct(
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $name = null,
    ) {
    }

    public static function phone(string $phone, ?string $name = null): self
    {
        return new self(phone: $phone, name: $name);
    }

    public static function email(string $email, ?string $name = null): self
    {
        return new self(email: $email, name: $name);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter(
            ['phone' => $this->phone, 'email' => $this->email, 'name' => $this->name],
            static fn (?string $v): bool => $v !== null,
        );
    }
}
