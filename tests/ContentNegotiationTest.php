<?php

declare(strict_types=1);

use CreativeCrafts\LaravelApiResponse\Helpers\ContentNegotiation;

covers(ContentNegotiation::class);

describe('ContentNegotiation', function () {
    it('returns json for application/json Accept header', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type('application/json'))->toBe('json');
    });

    it('returns xml for application/xml Accept header', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type('application/xml'))->toBe('xml');
    });

    it('returns xml for text/xml Accept header', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type('text/xml'))->toBe('xml');
    });

    it('returns json for unsupported Accept header', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type('application/unsupported'))->toBe('json');
    });

    it('returns json for empty Accept header', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type(''))->toBe('json');
    });

    it('handles multiple Accept headers and returns first supported type', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type('application/unsupported, application/xml, application/json'))->toBe('xml');
    });

    it('handles Accept headers with quality values', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type('application/json;q=0.9, application/xml;q=1.0'))->toBe('xml');
    });

    it('ignores whitespace in Accept headers', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type(' application/json , application/xml '))->toBe('xml');
    });

    it('is case-insensitive for Accept headers', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type('APPLICATION/JSON'))->toBe('json')
            ->and($negotiation->type('application/XML'))->toBe('xml');
    });

    it('returns json when all Accept types are unsupported', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type('application/pdf, image/png, text/html'))->toBe('json');
    });

    it('handles malformed Accept headers', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type('invalid,header,format'))->toBe('json');
    });

    it('prioritizes full mime types over partial matches', function () {
        $negotiation = new ContentNegotiation();
        expect($negotiation->type('application/json, application/*'))->toBe('json');
    });
});
