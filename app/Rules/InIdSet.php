<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * O(1) membership check for integer ids. Use instead of Rule::in on
 * wildcard (`items.*.id`) rules: Rule::in is re-stringified for every
 * expanded attribute, which is O(rows × ids) on large score batches.
 */
class InIdSet implements ValidationRule
{
    /** @var array<int, int> */
    private array $set;

    /**
     * @param  iterable<int|string>  $ids
     */
    public function __construct(iterable $ids)
    {
        $this->set = array_flip(array_map('intval', collect($ids)->all()));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || ! isset($this->set[(int) $value])) {
            $fail('validation.in')->translate();
        }
    }
}
