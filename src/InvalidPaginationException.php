<?php

declare(strict_types=1);

namespace Light\Db\GraphQLite\Mappers;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use InvalidArgumentException;

final class InvalidPaginationException extends InvalidArgumentException implements ClientAware, ProvidesExtensions
{
    public static function negative(string $argument): self
    {
        return new self('"' . $argument . '" must be greater than or equal to 0.');
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getExtensions(): array
    {
        return ['code' => 'INVALID_PAGINATION', 'category' => 'db_query'];
    }
}
