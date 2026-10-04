<?php

declare(strict_types=1);

namespace StrObj\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/** Separates control statements and returns while keeping their comments attached. */
final class StatementSpacingSniff implements Sniff
{
    /** Returns control-flow tokens whose surrounding spacing needs checking. */
    public function register(): array
    {
        return [
            T_IF, T_FOR, T_FOREACH, T_WHILE, T_DO, T_SWITCH, T_TRY, T_RETURN,
            T_ELSEIF, T_ELSE, T_CATCH, T_FINALLY,
        ];
    }

    /**
     * Adds a blank line without changing executable tokens or documentation.
     *
     * @param File $phpcsFile Current tokenized PHP file.
     * @param int  $stackPtr  Current statement's position in the token stack.
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);
        $branch = in_array($tokens[$stackPtr]['code'], [T_ELSEIF, T_ELSE, T_CATCH, T_FINALLY], true);
        $continuation = false;

        if (
            !$branch && $previous !== false
            && in_array($tokens[$previous]['code'], [T_SEMICOLON, T_CLOSE_CURLY_BRACKET], true)
        ) {
            // A do/while continuation belongs to the preceding block, not a new section.
            $owner = $tokens[$previous]['scope_condition'] ?? null;
            $continuation = $tokens[$stackPtr]['code'] === T_WHILE
            && $owner !== null && $tokens[$owner]['code'] === T_DO;

            if (!$continuation) {
                // Insert before attached comments rather than between a comment and its code.
                $anchor = $phpcsFile->findNext(T_WHITESPACE, $previous + 1, $stackPtr + 1, true);

                // Preserve a trailing comment on the preceding statement's line.
                if (
                    $anchor !== false && $tokens[$anchor]['code'] === T_COMMENT
                    && $tokens[$anchor]['line'] === $tokens[$previous]['line']
                ) {
                    $previous = $anchor;
                    $anchor = $phpcsFile->findNext(T_WHITESPACE, $previous + 1, $stackPtr + 1, true);
                }

                if ($anchor !== false) {
                    $this->ensureBlankLine($phpcsFile, $previous, $anchor);
                }
            }
        }

        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;

        if ($continuation) {
            $closer = $phpcsFile->findNext(T_SEMICOLON, $tokens[$stackPtr]['parenthesis_closer'] + 1);
        }

        if (
            $closer === null || $closer === false
            || (!$continuation && $tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET)
        ) {
            return;
        }

        $next = $phpcsFile->findNext(T_WHITESPACE, $closer + 1, null, true);
        $nextCode = $phpcsFile->findNext(Tokens::$emptyTokens, $closer + 1, null, true);

        if ($next === false || $nextCode === false) {
            return;
        }

        // Keep related branches together and avoid blank lines inside closing scopes.
        $related = in_array($tokens[$nextCode]['code'], [
            T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY, T_CLOSE_TAG, T_CLOSE_CURLY_BRACKET, T_SEMICOLON,
        ], true);
        $doWhile = $tokens[$stackPtr]['code'] === T_DO && $tokens[$nextCode]['code'] === T_WHILE;

        if ($related || $doWhile) {
            return;
        }

        // A same-line closing comment stays attached to the closing brace.
        if ($tokens[$next]['code'] === T_COMMENT && $tokens[$next]['line'] === $tokens[$closer]['line']) {
            $closer = $next;
            $next = $phpcsFile->findNext(T_WHITESPACE, $closer + 1, null, true);

            if ($next === false) {
                return;
            }
        }

        $this->ensureBlankLine($phpcsFile, $closer, $next);
    }

    /** Adds only missing line breaks, including fixes made earlier in the same pass. */
    private function ensureBlankLine(File $phpcsFile, int $previous, int $anchor): void
    {
        $tokens = $phpcsFile->getTokens();
        $added = substr_count($phpcsFile->fixer->getTokenContent($previous), "\n")
        - substr_count($tokens[$previous]['content'], "\n");
        $lastLine = $tokens[$previous]['line'] + substr_count(rtrim($tokens[$previous]['content'], "\r\n"), "\n");
        $missing = 2 - ($tokens[$anchor]['line'] - $lastLine + $added);

        if ($missing <= 0) {
            return;
        }

        $message = 'Separate this statement from previous code with a blank line.';

        if ($phpcsFile->addFixableError($message, $anchor, 'MissingBlankLine')) {
            for ($line = 0; $line < $missing; $line++) {
                $phpcsFile->fixer->addNewline($previous);
            }
        }
    }
}
