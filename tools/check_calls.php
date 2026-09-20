<?php

/**
 * Flags $this->method() calls that nothing declares.
 *
 * PHP only complains at the moment the line runs, so a controller with a
 * misspelled helper looks fine until somebody clicks the one button that
 * reaches it. This walks every class under app/, collects the methods it
 * declares plus those of its parents and traits, and reports calls that
 * resolve to nothing.
 *
 * Usage:  APP_ROOT=/path/to/project php check_calls.php
 * Exit:   0 when clean, 1 when something does not resolve.
 *
 * Prints nothing when clean, so it is quiet in a pipeline.
 */

$root = getenv('APP_ROOT') ?: getcwd();
$appDir = rtrim($root, '/').'/app';

if (! is_dir($appDir)) {
    fwrite(STDERR, "No app/ directory under {$root}\n");
    exit(2);
}

/**
 * Methods the framework provides that no file under app/ declares. A call to
 * one of these is fine; listing them is cheaper and more predictable than
 * parsing vendor/.
 */
$framework = [
    // Controller and middleware plumbing
    'middleware', 'authorize', 'authorizeForUser', 'validate', 'validateWith',
    'dispatch', 'dispatchNow', 'dispatchSync', 'callAction', 'getMiddleware',
    // Console commands
    'argument', 'arguments', 'option', 'options', 'info', 'line', 'comment',
    'error', 'warn', 'alert', 'table', 'ask', 'askWithCompletion', 'secret',
    'confirm', 'choice', 'anticipate', 'newLine', 'call', 'callSilent',
    'withProgressBar', 'output', 'getOutput', 'setHidden', 'fail', 'components',
    // Eloquent and query builder
    'belongsTo', 'hasMany', 'hasOne', 'belongsToMany', 'morphTo', 'morphMany',
    'morphOne', 'morphToMany', 'morphedByMany', 'hasManyThrough', 'hasOneThrough',
    'newQuery', 'save', 'delete', 'update', 'fill', 'forceFill', 'refresh',
    'fresh', 'load', 'loadMissing', 'loadCount', 'getKey', 'getKeyName',
    'getTable', 'getAttribute', 'setAttribute', 'getOriginal', 'isDirty',
    'wasChanged', 'touch', 'push', 'replicate', 'toArray', 'toJson', 'jsonSerialize',
    'casts', 'getRouteKeyName', 'resolveRouteBinding', 'newCollection',
    'newPivot', 'relationLoaded', 'getRelationValue', 'qualifyColumn',
    'getConnection', 'setConnection', 'getFillable', 'getMorphClass',
    'attributesToArray', 'relationsToArray', 'bootIfNotBooted', 'initializeTraits',
    // Notifications / auth / mail
    'notify', 'notifyNow', 'routeNotificationFor', 'can', 'cannot', 'hasVerifiedEmail',
    'markEmailAsVerified', 'sendEmailVerificationNotification', 'getAuthIdentifier',
    'getAuthPassword', 'getRememberToken', 'setRememberToken', 'getRememberTokenName',
    // Service providers
    'app', 'booted', 'booting', 'publishes', 'mergeConfigFrom', 'loadRoutesFrom',
    'loadViewsFrom', 'loadMigrationsFrom', 'loadTranslationsFrom', 'commands',
    'registerPolicies',
    // Requests
    'input', 'all', 'get', 'has', 'filled', 'boolean', 'string', 'integer',
    'file', 'hasFile', 'user', 'route', 'query', 'ip', 'session', 'expectsJson',
    'wantsJson', 'ajax', 'header', 'bearerToken', 'fullUrl', 'path', 'method',
    'isMethod', 'merge', 'only', 'except', 'collect', 'date', 'enum', 'when',
    // Migrations
    'up', 'down', 'getConnection',
];

$frameworkSet = array_flip($framework);

/** @return list<string> */
function phpFiles(string $dir): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $out[] = $file->getPathname();
        }
    }

    sort($out);

    return $out;
}

$files = phpFiles($appDir);

// Pass one: what every class declares, and what it extends or uses.
$declares = [];   // class => [method => true]
$extends = [];    // class => parent
$uses = [];       // class => [trait, ...]
$fileOfClass = [];

foreach ($files as $path) {
    $src = file_get_contents($path);

    if (! preg_match('/^\s*namespace\s+([^;]+);/m', $src, $ns)) {
        continue;
    }

    if (! preg_match('/^\s*(?:final\s+|abstract\s+)?(?:class|trait)\s+(\w+)/m', $src, $cls)) {
        continue;
    }

    $fqcn = trim($ns[1]).'\\'.$cls[1];
    $fileOfClass[$fqcn] = $path;

    preg_match_all('/function\s+(\w+)\s*\(/', $src, $methods);
    $declares[$fqcn] = array_flip($methods[1]);

    if (preg_match('/(?:class|trait)\s+\w+\s+extends\s+([\\\\\w]+)/', $src, $parent)) {
        $extends[$fqcn] = ltrim($parent[1], '\\');
    }

    preg_match_all('/^\s*use\s+([A-Z][\\\\\w, ]*);/m', $src, $traitUses);
    $traits = [];
    foreach ($traitUses[1] as $line) {
        foreach (explode(',', $line) as $t) {
            $traits[] = ltrim(trim($t), '\\');
        }
    }
    $uses[$fqcn] = $traits;
}

/**
 * Every method reachable on a class: its own, its parents', its traits'.
 * Short names are matched too, because a parent is often imported rather
 * than written out in full.
 */
function reachable(string $fqcn, array $declares, array $extends, array $uses, array $seen = []): array
{
    if (isset($seen[$fqcn]) || ! isset($declares[$fqcn])) {
        return [];
    }

    $seen[$fqcn] = true;
    $methods = $declares[$fqcn];

    foreach ($uses[$fqcn] ?? [] as $trait) {
        $target = resolve($trait, $declares);

        if ($target) {
            $methods += reachable($target, $declares, $extends, $uses, $seen);
        }
    }

    if (isset($extends[$fqcn])) {
        $target = resolve($extends[$fqcn], $declares);

        if ($target) {
            $methods += reachable($target, $declares, $extends, $uses, $seen);
        }
    }

    return $methods;
}

/** Matches a written name against the known classes, long form or short. */
function resolve(string $name, array $declares): ?string
{
    if (isset($declares[$name])) {
        return $name;
    }

    $short = substr(strrchr('\\'.$name, '\\'), 1);

    foreach (array_keys($declares) as $known) {
        if (substr(strrchr('\\'.$known, '\\'), 1) === $short) {
            return $known;
        }
    }

    return null;
}

// Pass two: every $this->foo() call, checked against what is reachable.
$problems = [];

foreach ($fileOfClass as $fqcn => $path) {
    $src = file_get_contents($path);
    $methods = reachable($fqcn, $declares, $extends, $uses);

    // A class that extends something outside app/ (a framework base class)
    // cannot be fully resolved, so unknown calls there are not reported.
    $parent = $extends[$fqcn] ?? null;
    $parentUnknown = $parent !== null && resolve($parent, $declares) === null;

    preg_match_all('/\$this->(\w+)\s*\(/', $src, $calls, PREG_OFFSET_CAPTURE);

    foreach ($calls[1] as [$name, $offset]) {
        if (isset($methods[$name]) || isset($frameworkSet[$name])) {
            continue;
        }

        if ($parentUnknown) {
            continue;
        }

        $line = substr_count($src, "\n", 0, $offset) + 1;
        $rel = str_replace(rtrim($root, '/').'/', '', $path);
        $problems[] = "{$rel}:{$line}  \$this->{$name}() is not declared in {$fqcn} or anything it extends";
    }
}

foreach ($problems as $problem) {
    echo $problem, "\n";
}

exit($problems === [] ? 0 : 1);
