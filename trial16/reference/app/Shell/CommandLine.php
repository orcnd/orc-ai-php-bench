<?php
declare(strict_types=1);
namespace App\Shell;

/** Parses the one-line commands of the admin console (a POSIX sh subset). See docs/SHELL.md. */
final class CommandLine
{
    /**
     * @return array{argv: list<string>, stdout: ?string, stdoutAppend: bool, stderr: ?string, stderrAppend: bool}
     */
    public function parse(string $line): array
    {
        $result = ['argv' => [], 'stdout' => null, 'stdoutAppend' => false, 'stderr' => null, 'stderrAppend' => false];
        $word = null;      // current word, null when between words
        $redirect = null;  // 'stdout' or 'stderr' while waiting for the target word
        $append = false;   // whether the pending redirect is '>>'
        $length = strlen($line);
        $finish = function () use (&$word, &$redirect, &$append, &$result): void {
            if ($word === null) {
                return;
            }
            if ($redirect !== null) {
                $result[$redirect] = $word;
                $result[$redirect . 'Append'] = $append;
                $redirect = null;
            } else {
                $result['argv'][] = $word;
            }
            $word = null;
        };
        for ($i = 0; $i < $length; $i++) {
            $c = $line[$i];
            if ($c === ' ' || $c === "\t") {
                $finish();
            } elseif ($c === "'") {
                $end = strpos($line, "'", $i + 1);
                if ($end === false) {
                    throw new SyntaxError('Unterminated quote');
                }
                $word = ($word ?? '') . substr($line, $i + 1, $end - $i - 1);
                $i = $end;
            } elseif ($c === '"') {
                $word = $word ?? '';
                for ($i++; $i < $length && $line[$i] !== '"'; $i++) {
                    if ($line[$i] === '\\' && $i + 1 < $length && strpos('"\\$`', $line[$i + 1]) !== false) {
                        $i++;
                    }
                    $word .= $line[$i];
                }
                if ($i >= $length) {
                    throw new SyntaxError('Unterminated quote');
                }
            } elseif ($c === '\\') {
                if ($i + 1 < $length) {
                    $word = ($word ?? '') . $line[++$i];
                }
            } elseif ($c === '>') {
                $target = 'stdout';
                if ($word === '1' || $word === '2') {
                    // "2>" only when the digit is a whole word so far: "a2>f" is the word "a2".
                    $target = $word === '2' ? 'stderr' : 'stdout';
                    $word = null;
                } else {
                    $finish();
                }
                if ($redirect !== null) {
                    throw new SyntaxError('Missing redirect target');
                }
                $redirect = $target;
                $append = $i + 1 < $length && $line[$i + 1] === '>';
                if ($append) {
                    $i++;
                }
            } else {
                $word = ($word ?? '') . $c;
            }
        }
        $finish();
        if ($redirect !== null) {
            throw new SyntaxError('Missing redirect target');
        }
        return $result;
    }
}
