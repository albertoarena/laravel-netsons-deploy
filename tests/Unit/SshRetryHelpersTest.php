<?php

declare(strict_types=1);

/**
 * Tests for the ssh_retry and scp_retry shell helper functions.
 *
 * These tests extract the helper functions from deploy.yml.stub and action.yml,
 * then execute them in a real bash shell with mocked ssh_with_opts/scp commands
 * to verify retry behavior.
 */
beforeEach(function () {
    // Extract the ssh-helpers block from deploy.yml.stub
    $stub = file_get_contents(__DIR__.'/../../stubs/workflows/deploy.yml.stub');
    preg_match("/cat > \/tmp\/ssh-helpers\.sh <<'HELPERS'\n(.*?)HELPERS/s", $stub, $matches);
    expect($matches)->toHaveCount(2, 'Could not extract ssh-helpers block from deploy.yml.stub');
    $this->stubHelpers = trim($matches[1]);

    // Extract the ssh-helpers block from action.yml
    $action = file_get_contents(__DIR__.'/../../action.yml');
    preg_match("/cat > \/tmp\/ssh-helpers\.sh <<'HELPERS'\n(.*?)HELPERS/s", $action, $matches);
    expect($matches)->toHaveCount(2, 'Could not extract ssh-helpers block from action.yml');
    $this->actionHelpers = trim($matches[1]);
});

describe('deploy.yml.stub ssh helpers', function () {
    it('ssh_retry retries on exit code 255', function () {
        $script = buildRetryTestScript($this->stubHelpers, 'ssh_retry', 255, 2);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(0)
            ->and($result['attempts'])->toBe(3);
    });

    it('ssh_retry does not retry on non-255 exit codes', function () {
        $script = buildRetryTestScript($this->stubHelpers, 'ssh_retry', 1, 1);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(1)
            ->and($result['attempts'])->toBe(1);
    });

    it('ssh_retry fails after max attempts exhausted', function () {
        $script = buildRetryTestScript($this->stubHelpers, 'ssh_retry', 255, 999);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(255)
            ->and($result['attempts'])->toBe(3);
    });

    it('ssh_retry returns 0 on first success', function () {
        $script = buildRetryTestScript($this->stubHelpers, 'ssh_retry', 255, 0);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(0)
            ->and($result['attempts'])->toBe(1);
    });

    it('scp_retry retries on exit code 255', function () {
        $script = buildScpRetryTestScript($this->stubHelpers, 255, 2);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(0)
            ->and($result['attempts'])->toBe(3);
    });

    it('scp_retry does not retry on non-255 exit codes', function () {
        $script = buildScpRetryTestScript($this->stubHelpers, 1, 1);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(1)
            ->and($result['attempts'])->toBe(1);
    });

    it('scp_retry fails after max attempts exhausted', function () {
        $script = buildScpRetryTestScript($this->stubHelpers, 255, 999);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(255)
            ->and($result['attempts'])->toBe(3);
    });
});

describe('action.yml ssh helpers', function () {
    it('ssh_retry retries on exit code 255', function () {
        $script = buildRetryTestScript($this->actionHelpers, 'ssh_retry', 255, 2);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(0)
            ->and($result['attempts'])->toBe(3);
    });

    it('ssh_retry does not retry on non-255 exit codes', function () {
        $script = buildRetryTestScript($this->actionHelpers, 'ssh_retry', 1, 1);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(1)
            ->and($result['attempts'])->toBe(1);
    });

    it('ssh_retry fails after max attempts exhausted', function () {
        $script = buildRetryTestScript($this->actionHelpers, 'ssh_retry', 255, 999);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(255)
            ->and($result['attempts'])->toBe(3);
    });

    it('scp_retry retries on exit code 255', function () {
        $script = buildScpRetryTestScript($this->actionHelpers, 255, 2);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(0)
            ->and($result['attempts'])->toBe(3);
    });

    it('scp_retry does not retry on non-255 exit codes', function () {
        $script = buildScpRetryTestScript($this->actionHelpers, 1, 1);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(1)
            ->and($result['attempts'])->toBe(1);
    });

    it('scp_retry fails after max attempts exhausted', function () {
        $script = buildScpRetryTestScript($this->actionHelpers, 255, 999);
        $result = runBashScript($script);

        expect($result['exit_code'])->toBe(255)
            ->and($result['attempts'])->toBe(3);
    });
});

describe('stub and action helpers are identical', function () {
    it('has matching ssh helper functions in both files', function () {
        // Normalize indentation — stub and action.yml may differ in leading spaces
        $normalize = fn (string $s) => implode("\n", array_map('trim', explode("\n", $s)));
        expect($normalize($this->stubHelpers))->toBe($normalize($this->actionHelpers));
    });
});

/**
 * Build a bash script that tests ssh_retry with a mock ssh_with_opts.
 *
 * @param  string  $helpers  The helper functions source code
 * @param  string  $function  The function to test (ssh_retry)
 * @param  int  $failCode  Exit code for failures
 * @param  int  $failCount  Number of times to fail before succeeding (999 = always fail)
 * @return string The complete bash script
 */
function buildRetryTestScript(string $helpers, string $function, int $failCode, int $failCount): string
{
    return <<<BASH
#!/bin/bash
export SSH_RETRIES=3
export SSH_RETRY_DELAY=0
export SSH_CONNECT_TIMEOUT=1

ATTEMPT_FILE=\$(mktemp)
echo "0" > "\$ATTEMPT_FILE"

# Source the helper functions first
{$helpers}

# Override ssh_with_opts AFTER sourcing so our mock takes precedence
ssh_with_opts() {
    local count=\$(cat "\$ATTEMPT_FILE")
    count=\$((count + 1))
    echo "\$count" > "\$ATTEMPT_FILE"
    if [ \$count -le {$failCount} ]; then
        return {$failCode}
    fi
    return 0
}

# Run the function under test
{$function} -p 65100 user@host echo test
exit_code=\$?

attempts=\$(cat "\$ATTEMPT_FILE")
rm -f "\$ATTEMPT_FILE"

echo "EXIT_CODE=\$exit_code"
echo "ATTEMPTS=\$attempts"
BASH;
}

/**
 * Build a bash script that tests scp_retry with a mock scp command.
 *
 * @param  string  $helpers  The helper functions source code
 * @param  int  $failCode  Exit code for failures
 * @param  int  $failCount  Number of times to fail before succeeding (999 = always fail)
 * @return string The complete bash script
 */
function buildScpRetryTestScript(string $helpers, int $failCode, int $failCount): string
{
    return <<<BASH
#!/bin/bash
export SSH_RETRIES=3
export SSH_RETRY_DELAY=0
export SSH_CONNECT_TIMEOUT=1

ATTEMPT_FILE=\$(mktemp)
echo "0" > "\$ATTEMPT_FILE"

# Override scp with a mock (as a function, takes precedence over the binary)
scp() {
    local count=\$(cat "\$ATTEMPT_FILE")
    count=\$((count + 1))
    echo "\$count" > "\$ATTEMPT_FILE"
    if [ \$count -le {$failCount} ]; then
        return {$failCode}
    fi
    return 0
}

# Source the helper functions (scp_retry will call our mocked scp)
{$helpers}

# Run scp_retry
scp_retry -P 65100 user@host:/remote/file /local/file
exit_code=\$?

attempts=\$(cat "\$ATTEMPT_FILE")
rm -f "\$ATTEMPT_FILE"

echo "EXIT_CODE=\$exit_code"
echo "ATTEMPTS=\$attempts"
BASH;
}

/**
 * Execute a bash script and parse the EXIT_CODE and ATTEMPTS from output.
 *
 * @return array{exit_code: int, attempts: int}
 */
function runBashScript(string $script): array
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'ssh_retry_test_');
    file_put_contents($tmpFile, $script);
    chmod($tmpFile, 0755);

    $output = [];
    exec("bash {$tmpFile} 2>/dev/null", $output, $exitCode);
    unlink($tmpFile);

    $result = ['exit_code' => 0, 'attempts' => 0];
    foreach ($output as $line) {
        if (preg_match('/^EXIT_CODE=(\d+)$/', $line, $m)) {
            $result['exit_code'] = (int) $m[1];
        }
        if (preg_match('/^ATTEMPTS=(\d+)$/', $line, $m)) {
            $result['attempts'] = (int) $m[1];
        }
    }

    return $result;
}
