<?php
declare(strict_types=1);
namespace App\Text;

/** Grammar-driven tokenizer used by the code highlighter of the help center. */
final class Tokenizer
{
    /**
     * @param list<array{0: string, 1: string}> $rules [token name, regex anchored with \G], tried in order
     * @return list<array{0: string, 1: string}> [token name, text]; unmatched characters become 'text'
     */
    public function tokenize(string $input, array $rules): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($input);
        while ($offset < $length) {
            $matched = false;
            foreach ($rules as [$name, $regex]) {
                // A rule that matches without consuming input is skipped, not a token.
                if (preg_match($regex, $input, $m, 0, $offset) === 1 && $m[0] !== '') {
                    $tokens[] = [$name, $m[0]];
                    $offset += strlen($m[0]);
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $tokens[] = ['text', $input[$offset]];
                $offset++;
            }
        }
        return $tokens;
    }
}
