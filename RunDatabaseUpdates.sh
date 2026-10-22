#!/usr/bin/env bash

set -u

scriptDirectory="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
phpBinary="${DAMWIDI_PHP_BINARY:-/usr/local/bin/php}"
jobs=(
    updateBivioTransactions
    updatePerformanceData
    updateValueTable
    updateHistoryTable
)
failedJobs=()

if [[ ! -x "$phpBinary" ]]; then
    printf 'PHP executable is not available: %s\n' "$phpBinary" >&2
    exit 1
fi

cd "$scriptDirectory" || exit 1

if [[ ! -f damwidiMain.php ]]; then
    printf 'Application entry point not found: %s/damwidiMain.php\n' \
        "$scriptDirectory" >&2
    exit 1
fi

pipelineStartedAt=$(date +%s)
printf 'update damwidi databases start: %s\n' "$(date '+%Y-%m-%d %T')"

for job in "${jobs[@]}"; do
    printf 'starting %s\n' "$job"

    if "$phpBinary" damwidiMain.php "$job"; then
        printf 'completed %s\n' "$job"
    else
        jobExitStatus=$?
        failedJobs+=("$job")
        printf 'failed %s (exit code: %d)\n' "$job" "$jobExitStatus" >&2

        break
    fi
done

pipelineDuration=$(( $(date +%s) - pipelineStartedAt ))
failedJobList=''
summaryStatus='successful'
exitStatus=0

if (( ${#failedJobs[@]} > 0 )); then
    failedJobList=$(IFS=,; printf '%s' "${failedJobs[*]}")
    summaryStatus='fail'
    exitStatus=1
fi

if ! "$phpBinary" damwidiMain.php logDatabaseUpdateSummary "$summaryStatus" "$failedJobList" "$pipelineDuration"; then
    printf 'failed to publish database update summary log\n' >&2
    exitStatus=1
fi

printf 'update damwidi databases end:   %s\n' "$(date '+%Y-%m-%d %T')"
exit "$exitStatus"
