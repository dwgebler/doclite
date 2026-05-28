<?php

declare(strict_types=1);

namespace Gebler\Doclite\Internal;

use InvalidArgumentException;

/**
 * Strict JSON-path-identifier validator for use in raw SQL contexts.
 *
 * Accepts:
 *   - segments of [A-Za-z_][A-Za-z0-9_]*
 *   - optional [<digits>] array indices on a segment
 *   - segments joined by '.'
 *   - optional trailing '[]' for the path-style query branch
 *
 * Rejects everything else, including ", ', \\, \$, ;, parens, etc.
 */
final class JsonPath
{
    private const MAX_LENGTH = 255;
    private const SEGMENT = '[A-Za-z_][A-Za-z0-9_]*(?:\[[0-9]+\])?';
    private const PATTERN = '/^' . self::SEGMENT
        . '(?:\.' . self::SEGMENT . ')*'
        . '(?:\[\])?$/';

    /**
     * Validate a JSON-path-style identifier. Returns the identifier unchanged
     * on success; throws InvalidArgumentException on failure.
     */
    public static function validate(string $name): string
    {
        if ($name === '' || strlen($name) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid field name "%s": empty or longer than %d characters',
                    $name,
                    self::MAX_LENGTH
                )
            );
        }
        if (preg_match(self::PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Invalid field name "%s"', $name)
            );
        }
        return $name;
    }
}
