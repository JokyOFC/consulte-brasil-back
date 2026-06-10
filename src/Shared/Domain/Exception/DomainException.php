<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exception;

use RuntimeException;

/**
 * Base de todas as exceções de regra de negócio.
 *
 * Vive na camada de Domínio e não conhece HTTP/Laravel. A tradução para
 * status codes acontece na borda (Infrastructure/Http).
 */
abstract class DomainException extends RuntimeException {}
