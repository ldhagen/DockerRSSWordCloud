// Enhanced cycling logic - replace the array_slice line in auto_collect.php

// Get last processed index from state file
$state_file = DATA_DIR . '/collection_state.json';
$state = ['last_index' => 0];
if (file_exists($state_file)) {
    $state = json_decode(file_get_contents($state_file), true) ?: $state;
}

$total_feeds = count($feeds_data['feeds']);
$start_index = $state['last_index'];

// Calculate feeds to process this run
$feeds_to_process = [];
for ($i = 0; $i < $MAX_FEEDS_PER_RUN && count($feeds_to_process) < $total_feeds; $i++) {
    $index = ($start_index + $i) % $total_feeds;
    $feeds_to_process[] = $feeds_data['feeds'][$index];
}

// Update state for next run
$next_index = ($start_index + $MAX_FEEDS_PER_RUN) % $total_feeds;
$state['last_index'] = $next_index;
$state['cycle_completed'] = ($next_index < $start_index || ($start_index + $MAX_FEEDS_PER_RUN) >= $total_feeds);
file_put_contents($state_file, json_encode($state));

log_message("Processing feeds $start_index to " . ($start_index + count($feeds_to_process) - 1) . " (cycle " . ($state['cycle_completed'] ? "completed" : "continuing") . ")", 'INFO');