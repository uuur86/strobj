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
        $continuation = $this->separateFromPrevious($phpcsFile, $stackPtr);
        $closer = $this->findStatementEnd($phpcsFile, $stackPtr, $continuation);

        if ($closer !== null) {
            $this->separateFromNext($phpcsFile, $stackPtr, $closer);
        }
    }

    /**
     * Separates a statement from the preceding statement or block.
     *
     * @param File $phpcsFile Current tokenized PHP file.
     * @param int  $stackPtr  Current statement's position in the token stack.
     *
     * @return bool Whether the statement is the while condition of a do/while loop.
     */
    private function separateFromPrevious(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);
        $branch = in_array($tokens[$stackPtr]['code'], [T_ELSEIF, T_ELSE, T_CATCH, T_FINALLY], true);

        if (
            $branch || $previous === false
            || !in_array($tokens[$previous]['code'], [T_SEMICOLON, T_CLOSE_CURLY_BRACKET], true)
        ) {
            return false;
        }

        // A do/while continuation belongs to the preceding block, not a new section.
        $owner = $tokens[$previous]['scope_condition'] ?? null;

        if ($tokens[$stackPtr]['code'] === T_WHILE && $owner !== null && $tokens[$owner]['code'] === T_DO) {
            return true;
        }

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

        return false;
    }

    /**
     * Finds the closing brace of a block statement or the semicolon after a do/while condition.
     *
     * @param File $phpcsFile    Current tokenized PHP file.
     * @param int  $stackPtr     Current statement's position in the token stack.
     * @param bool $continuation Whether the statement is a do/while condition.
     *
     * @return int|null Null for statements without a block, such as return.
     */
    private function findStatementEnd(File $phpcsFile, int $stackPtr, bool $continuation): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if ($continuation) {
            $semicolon = $phpcsFile->findNext(T_SEMICOLON, $tokens[$stackPtr]['parenthesis_closer'] + 1);

            return $semicolon === false ? null : $semicolon;
        }

        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;

        return $closer !== null && $tokens[$closer]['code'] === T_CLOSE_CURLY_BRACKET ? $closer : null;
    }

    /**
     * Separates the code that follows a statement's end from that statement.
     *
     * @param File $phpcsFile Current tokenized PHP file.
     * @param int  $stackPtr  Current statement's position in the token stack.
     * @param int  $closer    The statement's closing brace or semicolon.
     */
    private function separateFromNext(File $phpcsFile, int $stackPtr, int $closer): void
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(T_WHITESPACE, $closer + 1, null, true);
        $nextCode = $phpcsFile->findNext(Tokens::$emptyTokens, $closer + 1, null, true);

        if ($next === false || $nextCode === false || $this->continuesStatement($tokens, $stackPtr, $nextCode)) {
            return;
        }

        // A same-line closing comment stays attached to the closing brace.
        if ($tokens[$next]['code'] === T_COMMENT && $tokens[$next]['line'] === $tokens[$closer]['line']) {
            $closer = $next;
            $next = $phpcsFile->findNext(T_WHITESPACE, $closer + 1, null, true);
        }

        if ($next !== false) {
            $this->ensureBlankLine($phpcsFile, $closer, $next);
        }
    }

    /**
     * Keeps related branches together and avoids blank lines inside closing scopes.
     *
     * @param array $tokens   Token stack of the current file.
     * @param int   $stackPtr Current statement's position in the token stack.
     * @param int   $nextCode Position of the next code token after the statement.
     *
     * @return bool
     */
    private function continuesStatement(array $tokens, int $stackPtr, int $nextCode): bool
    {
        $related = in_array($tokens[$nextCode]['code'], [
            T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY, T_CLOSE_TAG, T_CLOSE_CURLY_BRACKET, T_SEMICOLON,
        ], true);

        return $related || ($tokens[$stackPtr]['code'] === T_DO && $tokens[$nextCode]['code'] === T_WHILE);
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
