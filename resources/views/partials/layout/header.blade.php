{{-- Header --}}
<nav class="app-header navbar navbar-expand bg-body border-bottom">
    <div class="container-fluid">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" href="#" data-lte-toggle="sidebar" role="button" title="{{ ui('toggle_sidebar') }}">
                    <i class="bi bi-list fs-4"></i>
                </a>
            </li>
        </ul>

        {{-- Global menu search (dropdown of matches; click navigates. No backend.) --}}
        <ul class="navbar-nav ms-auto flex-grow-1 px-2 d-none d-md-flex">
            <li class="nav-item w-100 position-relative" style="max-width:420px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-body border-0"><i class="bi bi-search"></i></span>
                    <input type="search" id="menu-search" class="form-control bg-body border-0" placeholder="{{ ui('search_menu') }}" autocomplete="off" aria-label="{{ ui('search_menu') }}" aria-expanded="false" aria-controls="menu-search-results">
                </div>
                <ul id="menu-search-results" class="dropdown-menu w-100 py-1 shadow-sm" style="display:none;max-height:320px;overflow:auto"></ul>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center">
            {{-- Theme toggle --}}
            <li class="nav-item">
                <button class="btn btn-link nav-link px-2" id="theme-toggle" type="button" title="{{ ui('toggle_theme') }}" aria-label="{{ ui('toggle_theme') }}">
                    <i class="bi bi-moon-stars fs-5" id="theme-icon"></i>
                </button>
            </li>

            {{-- Notifications --}}
            <li class="nav-item dropdown">
                <a class="nav-link position-relative px-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications" aria-label="Notifications">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle border" style="width:36px;height:36px;background:var(--lbp-surface-2);border-color:var(--lbp-border)">
                        <i class="bi bi-bell fs-5"></i>
                    </span>
                    @if (($notifications['unread'] ?? 0) > 0)
                        <span class="position-absolute badge rounded-pill text-bg-danger" style="top:2px;right:2px;font-size:10px;padding:2px 5px">{{ $notifications['unread'] }}</span>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-lg py-0" style="width:320px">
                    <span class="dropdown-item dropdown-header d-flex justify-content-between"><span>{{ ui('recent_activity') }}</span><span class="badge bg-primary-subtle text-primary">{{ $notifications['items']->count() }}</span></span>
                    <div class="dropdown-divider my-0"></div>
                    <div style="max-height:280px;overflow:auto">
                    @forelse ($notifications['items'] as $n)
                        <a href="{{ route('audit.index') }}" class="dropdown-item py-2 border-bottom" style="white-space:normal">
                            <div class="d-flex gap-2">
                                <i class="bi bi-activity text-primary mt-1"></i>
                                <div class="flex-grow-1">
                                    <div class="small">{{ \Illuminate\Support\Str::limit($n->data['label'] ?? ($n->data['action'] ?? 'notification'), 40) }}</div>
                                    <div class="text-muted" style="font-size:11px">{{ $n->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <span class="dropdown-item text-muted">{{ ui('no_activity_yet') }}</span>
                    @endforelse
                    </div>
                    <div class="dropdown-divider my-0"></div>
                    <a href="{{ route('notifications.index') }}" class="dropdown-item dropdown-footer text-center text-primary">{{ ui('view_all_notifications') }}</a>
                </div>
            </li>

            {{-- User menu (native <details>, no JS dependency) --}}
            @auth
            <details class="nav-item dropdown user-menu">
                <summary class="nav-link d-flex align-items-center gap-2" aria-label="User menu">
                    <span class="avatar avatar-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:34px;height:34px">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="d-none d-md-inline fw-medium">{{ auth()->user()->name }}</span>
                    <i class="bi bi-chevron-down small opacity-75"></i>
                </summary>
                <ul class="dropdown-menu dropdown-menu-end py-1" style="min-width:220px">
                    <li class="dropdown-item-text d-flex align-items-center gap-2 pb-2">
                        <span class="avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:38px;height:38px">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <div class="text-truncate">
                            <div class="fw-medium">{{ auth()->user()->name }}</div>
                            <div class="text-muted small text-truncate" style="max-width:150px">{{ auth()->user()->email }}</div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-0"></li>
                    <li><a href="{{ route('profile.show') }}" class="dropdown-item py-2"><i class="bi bi-person me-2"></i> {{ ui('profile') }}</a></li>
                    @can('session.view')
                    <li><a href="{{ route('sessions.index') }}" class="dropdown-item py-2"><i class="bi bi-pc-display me-2"></i> {{ ui('sessions') }}</a></li>
                    @endcan
                    @can('feature.manage')
                    <li><a href="{{ route('settings.system') }}" class="dropdown-item py-2"><i class="bi bi-gear me-2"></i> {{ ui('system_settings') }}</a></li>
                    @endcan
                    <li><hr class="dropdown-divider my-0"></li>
                    <li class="dropdown-item-text pb-1">
                        <div class="text-muted small text-uppercase px-2">{{ __('messages.language') }}</div>
                        <div class="d-flex gap-1 px-2 pt-1">
                            <form method="POST" action="{{ route('locale.update') }}" class="flex-fill">
                                @csrf
                                <input type="hidden" name="locale" value="en">
                                <button type="submit" class="btn btn-sm {{ app()->getLocale() === 'en' ? 'btn-primary' : 'btn-outline-secondary' }} w-100">{{ __('messages.english') }}</button>
                            </form>
                            <form method="POST" action="{{ route('locale.update') }}" class="flex-fill">
                                @csrf
                                <input type="hidden" name="locale" value="id">
                                <button type="submit" class="btn btn-sm {{ app()->getLocale() === 'id' ? 'btn-primary' : 'btn-outline-secondary' }} w-100">{{ __('messages.indonesian') }}</button>
                            </form>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-0"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item py-2 text-danger w-100 text-start"><i class="bi bi-box-arrow-right me-2"></i> {{ ui('logout') }}</button>
                        </form>
                    </li>
                </ul>
            </details>
            @endauth
        </ul>
    </div>
</nav>
