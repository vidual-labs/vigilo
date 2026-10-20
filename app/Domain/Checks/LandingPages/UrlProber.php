<?php

namespace App\Domain\Checks\LandingPages;

/**
 * Fetches a landing page the way a visitor's browser would request it:
 * a plain GET, redirects followed hop by hop. It never submits a form,
 * never posts anything and never executes scripts.
 */
interface UrlProber
{
    public function probe(string $url): ProbeResult;
}
