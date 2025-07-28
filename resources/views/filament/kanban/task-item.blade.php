<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 shadow-sm hover:shadow-md transition-shadow cursor-pointer"
     wire:click="$dispatch('open-task-modal', { taskId: {{ $record->id }} })">

    <!-- Task Title -->
    <div class="flex items-start justify-between mb-2">
        <h3 class="font-medium text-gray-900 dark:text-gray-100 text-sm leading-tight">
            {{ $record->title }}
        </h3>

        @if($record->is_github_issue)
            <div class="flex-shrink-0 ml-2">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                              d="M10 0C4.477 0 0 4.484 0 10.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0110 4.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.203 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.921.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0020 10.017C20 4.484 15.522 0 10 0z"
                              clip-rule="evenodd"></path>
                    </svg>
                    #{{ $record->github_issue_number }}
                </span>
            </div>
        @endif
    </div>

    <!-- Task Description (if exists) -->
    @if($record->description)
        <p class="text-gray-600 dark:text-gray-400 text-xs mb-2 line-clamp-2">
            {{ Str::limit($record->description, 80) }}
        </p>
    @endif

    <!-- GitHub Labels -->
    @if($record->github_issue_labels && count($record->github_issue_labels) > 0)
        <div class="flex flex-wrap gap-1 mb-2">
            @foreach(collect($record->github_issue_labels)->take(3) as $label)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                      style="background-color: #{{ $label['color'] ?? 'gray' }}20; color: #{{ $label['color'] ?? 'gray' }};">
                    {{ $label['name'] }}
                </span>
            @endforeach
            @if(count($record->github_issue_labels) > 3)
                <span class="text-xs text-gray-500">+{{ count($record->github_issue_labels) - 3 }} more</span>
            @endif
        </div>
    @endif

    <!-- Bottom Row: Repository, Assignee, Dates -->
    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
        <div class="flex items-center space-x-2">
            @if($record->repository)
                <span class="inline-flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                    </svg>
                    {{ $record->repository->name }}
                </span>
            @endif

            @if($record->github_assignee)
                <span class="inline-flex items-center">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                              clip-rule="evenodd"></path>
                    </svg>
                    {{ $record->github_assignee }}
                </span>
            @endif
        </div>

        <div class="flex items-center space-x-1">
            @if($record->github_created_at)
                <span title="Created {{ $record->github_created_at->format('M j, Y') }}">
                    {{ $record->github_created_at->diffForHumans() }}
                </span>
            @else
                <span title="Created {{ $record->created_at->format('M j, Y') }}">
                    {{ $record->created_at->diffForHumans() }}
                </span>
            @endif
        </div>
    </div>

    <!-- Progress indicator for GitHub sync status -->
    @if($record->is_github_issue)
        <div class="mt-2 w-full bg-gray-200 rounded-full h-1">
            <div class="bg-purple-600 h-1 rounded-full"
                 style="width: {{ $record->github_issue_state === 'closed' ? '100' : '60' }}%"></div>
        </div>
    @endif
</div> 