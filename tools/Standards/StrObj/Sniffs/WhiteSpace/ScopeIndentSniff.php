<?php

declare(strict_types=1);

namespace StrObj\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\Generic\Sniffs\WhiteSpace\ScopeIndentSniff as NativeScopeIndentSniff;

/** Enforces exact PHP indentation while allowing HTML template attribute alignment. */
final class ScopeIndentSniff extends NativeScopeIndentSniff
{
    /** @var int[] Preserve alignment inside ordinary multiline comments. */
    public $ignoreIndentationTokens = [T_COMMENT];

    /** Uses the native PSR-12 scope formatter with a policy determined by the file's tokens. */
    public function process(File $phpcsFile, $stackPtr)
    {
        $this->exact = true;

        foreach ($phpcsFile->getTokens() as $token) {
            if ($token['code'] === T_INLINE_HTML && trim($token['content']) !== '') {
                $this->exact = false;
                break;
            }
        }

        return parent::process($phpcsFile, $stackPtr);
    }
}
