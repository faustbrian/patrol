<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

/**
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ResourceFixture
{
    public function __construct(
        public string $id,
        public string $type,
        public array $attributes = [],
    ) {}

    public static function document(string $id = 'document-1', array $attributes = []): self
    {
        return new self($id, 'document', $attributes);
    }

    public static function article(string $id = 'article-1', array $attributes = []): self
    {
        return new self($id, 'article', $attributes);
    }

    public function withAttribute(string $key, mixed $value): self
    {
        return new self(
            $this->id,
            $this->type,
            [...$this->attributes, $key => $value],
        );
    }
}
