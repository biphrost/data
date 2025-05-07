#!/usr/bin/env php

<?php

/**
 * A quick and ugly hack to more conveniently add content to corpus files.
 */

$arguments = $argv;
array_shift($arguments);

foreach ($arguments as $corpus_file) {
    $corpus_path = @realpath($corpus_file);
    if ( $corpus_path === false ) {
        fwrite(STDERR, "File does not exist: $corpus_file\n");
        continue;
    }
    if ( ! @is_readable($corpus_path) ) {
        fwrite(STDERR, "File is not readable: $corpus_file\n");
        continue;
    }
    if ( ! @is_writable($corpus_path) ) {
        fwrite(STDERR, "File is not writable: $corpus_file\n");
        continue;
    }
    //  Read file into an array of lines and trim whitespace from the beginning
    //  and end of each line.
    $lines = array_map('trim', file($corpus_path));
    //  Delete any lines that do not contain lowercase alphabetic characters.
    //  This helps to filter out tables, code, ascii graphics, and so on.
    $lines = preg_replace('/^[^a-z]+$/', '', $lines);
    //  Merge the lines and apply a regex to them to unwrap them.
    //  (Much of the input corpus has lines hard-wrapped to ~80 chars.)
    $lines = preg_replace('/\n(?!\s)/', ' ', implode("\n", $lines));
    //  Replace underlines with a single space.
    $lines = preg_replace('/_+/', ' ', $lines);
    //  Some of the input corpus is manually justified (?!), so remove runs
    //  of whitespace.
    $lines = preg_replace('/[\t ]+/', ' ', $lines);
    //  Break the content into individual lines again.
    $lines = explode("\n", $lines);
    //  Re-trim them (the above transformations may have added some whitespace).
    $lines = array_map('trim', $lines);
    //  Delete any lines that are less than 100 characters long. This helps to
    //  filter out things like paragraph headers, titles, and so on.
    $lines = array_filter($lines, function($line){
        return strlen($line) > 100;
    });
    //  Delete any lines that contain a small ratio of lowercase alpha characters.
    //  These are mostly going to be tables of values, symbols, code snippets, etc.
    $lines = array_filter($lines, function($line){
        $lowercase = preg_replace('/[^a-z]/', '', $line);
        return strlen($lowercase) / strlen($line) > 0.7;
    });
    //  Delete any lines that contain something that looks like an email address.
    //  I want to keep this data highly anonymized, and we have enough corpus data
    //  to just throw out some of it.
    //  This also does a super nice job of filtering out the SMTP headers that
    //  are included in some of the input data.
    $lines = preg_replace('/^.*[^\s]+\s*@\s*[^\s]+\.\s*[^\s]+.*$/', '', $lines);
    //  Likewise, delete any lines that contain something that looks like a
    //  phone number.
    $lines = preg_replace('/^.*(\(?[0-9]{3}\)?\s*)?[0-9]{3}[ -][0-9]{4}.*$/', '', $lines);
    //  Delete any lines that look like they are part of a list, footnote, etc.
    $lines = preg_replace('/^(\[|II+|ii+|\*[^A-Za-z]|[0-9]+\.).*$/', '', $lines);
    //  Delete any blank lines.
    $lines = array_filter($lines);
    //  Ensure that all lines are unique. Duplicate lines might sneak in either
    //  by operator error (importing the same source file twice) or by the
    //  transformations done above.
    $lines = array_unique($lines);
    //  Shuffle the lines! This makes git commits kind of nasty, but also helps
    //  ensure that these corpus files keep generating fresh new outputs.
    shuffle($lines);
    //  Append a "\n".
    $lines[] = "\n";
    //  Spit the final result back into the original input file.
    file_put_contents($corpus_path, implode("\n\n", $lines));
}
