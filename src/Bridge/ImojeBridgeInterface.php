<?php

/*
 * This file was created by developers working at BitBag
 * Do you need more information about us and what we do? Visit our https://bitbag.io website!
 * We are hiring developers from all over the world. Join us and start your new, exciting adventure and become part of us: https://bitbag.io/career
*/

declare(strict_types=1);

namespace Fronty\SyliusIMojePlugin\Bridge;

interface ImojeBridgeInterface
{
    /** Signature hash algorithm */
    public const HASH_METHOD = 'sha256';

    /** Sandbox and production API endpoint URLs */
    public const URL_PRODUCTION = 'https://paywall.imoje.pl/pl/payment';
    public const URL_SANDBOX = 'https://sandbox.paywall.imoje.pl/pl/payment';

    /** Response statuses according to which to mark payment status */
    public const STATUS_SETTLED = 'settled';
    public const STATUS_REJECTED = 'rejected';

    public const COMPLETED_STATUS = 'completed';

    public const FAILED_STATUS = 'failed';

    public const CANCELLED_STATUS = 'cancelled';

    public const CREATED_STATUS = 'created';

    public function getTrnRegisterUrl(): string;

    public function getTrnRequestUrl(string $token): string;

    public function getTrnVerifyUrl(): string;

    public function getHostForEnvironment(): string;

    public function setAuthorizationData(
        string $merchantId,
        string $crcKey,
        string $environment = self::SANDBOX_ENVIRONMENT
    ): void;

    public function createSign(array $parameters): string;

    public function trnRegister(array $posData): string;

    public function trnVerify(array $posData): bool;

    public function request(array $posData, string $url): array;
}
