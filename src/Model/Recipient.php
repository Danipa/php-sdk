<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

final class Recipient
{
    public function __construct(
        public readonly ?string $phone = null,
        public readonly ?string $accountNumber = null,
        public readonly ?string $bankCode = null,
        public readonly ?string $name = null,
    ) {
    }

    public static function phone(string $phone, ?string $name = null): self
    {
        return new self(phone: $phone, name: $name);
    }

    public static function bankAccount(string $accountNumber, string $bankCode, ?string $name = null): self
    {
        return new self(accountNumber: $accountNumber, bankCode: $bankCode, name: $name);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            accountNumber: isset($data['accountNumber']) ? (string) $data['accountNumber'] : null,
            bankCode: isset($data['bankCode']) ? (string) $data['bankCode'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter(
            [
                'phone' => $this->phone,
                'accountNumber' => $this->accountNumber,
                'bankCode' => $this->bankCode,
                'name' => $this->name,
            ],
            static fn (?string $v): bool => $v !== null,
        );
    }
}
