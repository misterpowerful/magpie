<?php

declare(strict_types=1);

use App\Support\HtmlTextExtractor;

beforeEach(function () {
    $this->extractor = new HtmlTextExtractor;
});

test('extracts visible text and drops script/style/nav/footer chrome', function () {
    $html = <<<'HTML'
        <html>
        <head><style>.hidden { display: none; }</style></head>
        <body>
            <nav>Home About Contact</nav>
            <header>Acme Careers</header>
            <main>
                <h1>Senior PHP Engineer</h1>
                <p>We are hiring a remote PHP engineer with Laravel experience.</p>
                <script>console.log('tracking pixel fired');</script>
            </main>
            <footer>&copy; 2026 Acme Inc.</footer>
        </body>
        </html>
        HTML;

    $text = $this->extractor->extractText($html);

    expect($text)->toContain('Senior PHP Engineer');
    expect($text)->toContain('remote PHP engineer with Laravel experience');
    expect($text)->not->toContain('tracking pixel');
    expect($text)->not->toContain('Home About Contact');
    expect($text)->not->toContain('Acme Careers');
    expect($text)->not->toContain('Acme Inc.');
});

test('collapses excessive blank lines left behind by stripped blocks', function () {
    $html = '<p>First paragraph.</p><script>var x = 1;</script><script>var y = 2;</script><p>Second paragraph.</p>';

    $text = $this->extractor->extractText($html);

    expect($text)->not->toMatch('/\n{3,}/');
});

test('empty markup produces an empty string, not an error', function () {
    expect($this->extractor->extractText(''))->toBe('');
    expect($this->extractor->extractText('   '))->toBe('');
});
