<aside class="app-sidebar sidebar bg-body-secondary shadow">
    <div class="sidebar-brand">
        <a href="{{ url('/') }}" class="brand-link">
            <i class="bi bi-hexagon-fill brand-image text-primary"></i>
            <span class="brand-text fw-light">{{ config('app.name', 'Laravel') }}</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-3 sidebar-nav">
            <ul class="nav sidebar-menu nav-indent flex-column" data-lte-toggle="treeview" data-accordion="false">
                <li class="nav-header">{{ ui('main_menu') }}</li>
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" data-menu-text="{{ ui('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-speedometer2"></i> <span>{{ ui('dashboard') }}</span>
                    </a>
                </li>

                {{-- Access Management --}}
                @php
                    // Root-cause: hide the whole parent when no child is visible.
                    // Visibility rule = child menu visible iff permission AND feature flag
                    // both pass (mirrors the nested @can/@feature on each child below).
                    // Parent visible = OR of all children. No new permission created.
                    $amVisible = collect([
                        ['user.view', 'users'],
                        ['role.view', 'roles'],
                        ['permission.view', 'permissions'],
                    ])->contains(fn ($c) => auth()->user()->can($c[0]) && \Laravel\Pennant\Feature::active($c[1]));
                @endphp
                @if ($amVisible)
                    @php
                        $amActive = request()->routeIs('users.*','roles.*','permissions.*');
                        $amClass  = $amActive ? 'menu-open' : '';
                        $amOn     = $amActive ? 'active' : '';
                        $amExpand = $amActive ? 'true' : 'false';
                    @endphp
                    <li class="nav-item {{ $amClass }}">
                        <a href="#" class="nav-link {{ $amOn }}" aria-expanded="{{ $amExpand }}"><i class="nav-icon bi bi-shield-lock"></i> <span>{{ ui('access_management') }}</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                        <ul class="nav nav-treeview">
                            @can('user.view')
                            @feature('users')
                            <li class="nav-item"><a href="{{ route('users.index') }}" data-menu-text="{{ ui('users') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"><i class="nav-icon bi bi-people"></i> <span>{{ ui('users') }}</span></a></li>
                            @endfeature
                            @endcan
                            @can('role.view')
                            @feature('roles')
                            <li class="nav-item"><a href="{{ route('roles.index') }}" data-menu-text="{{ ui('roles') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}"><i class="nav-icon bi bi-shield"></i> <span>{{ ui('roles') }}</span></a></li>
                            @endfeature
                            @endcan
                            @can('permission.view')
                            @feature('permissions')
                            <li class="nav-item"><a href="{{ route('permissions.index') }}" data-menu-text="{{ ui('permissions') }}" class="nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}"><i class="nav-icon bi bi-key"></i> <span>{{ ui('permissions') }}</span></a></li>
                            @endfeature
                            @endcan
                        </ul>
                    </li>
                @endif

                {{-- Monitoring --}}
                @php
                    // Root-cause: hide the whole parent when no child is visible.
                    // Parent visible = OR of all children (permission AND feature flag).
                    $monVisible = collect([
                        ['audit.view', 'audit'],
                        ['logs.view', 'logs'],
                        ['telescope.view', 'telescope'],
                        ['periscope.view', 'periscope'],
                    ])->contains(fn ($c) => auth()->user()->can($c[0]) && \Laravel\Pennant\Feature::active($c[1]));
                @endphp
                @if ($monVisible)
                    @php
                        $monActive  = request()->routeIs('audit.*','logs.*') || request()->is('telescope*','periscope*');
                        $monClass   = $monActive ? 'menu-open' : '';
                        $monOn      = $monActive ? 'active' : '';
                        $monExpand  = $monActive ? 'true' : 'false';
                    @endphp
                    <li class="nav-item {{ $monClass }}">
                        <a href="#" class="nav-link {{ $monOn }}" aria-expanded="{{ $monExpand }}"><i class="nav-icon bi bi-activity"></i> <span>{{ ui('monitoring') }}</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                        <ul class="nav nav-treeview">
                            @can('audit.view')
                            @feature('audit')
                            <li class="nav-item"><a href="{{ route('audit.index') }}" data-menu-text="{{ ui('audit_log') }}" class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}"><i class="nav-icon bi bi-journal-text"></i> <span>{{ ui('audit_log') }}</span></a></li>
                            @endfeature
                            @endcan
                            @can('logs.view')
                            @feature('logs')
                            <li class="nav-item"><a href="{{ route('logs.index') }}" data-menu-text="{{ ui('logs') }}" class="nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}"><i class="nav-icon bi bi-file-earmark-text"></i> <span>{{ ui('logs') }}</span></a></li>
                            @endfeature
                            @endcan
                            @can('telescope.view')
                            @feature('telescope')
                            <li class="nav-item"><a href="{{ url('/telescope') }}" data-menu-text="{{ ui('telescope') }}" target="_blank" rel="noopener noreferrer" class="nav-link {{ request()->is('telescope*') ? 'active' : '' }}"><i class="nav-icon bi bi-binoculars"></i> <span>{{ ui('telescope') }}</span></a></li>
                            @endfeature
                            @endcan
                            @can('periscope.view')
                            @feature('periscope')
                            <li class="nav-item"><a href="{{ url('/periscope') }}" data-menu-text="{{ ui('periscope') }}" target="_blank" rel="noopener noreferrer" class="nav-link {{ request()->is('periscope*') ? 'active' : '' }}"><i class="nav-icon bi bi-funnel"></i> <span>{{ ui('periscope') }}</span></a></li>
                            @endfeature
                            @endcan
                        </ul>
                    </li>
                @endif

                {{-- System / Settings --}}
                @php($setActive = request()->routeIs('sessions.*','api-tokens.*','features.*','translations.*','plans.*','profile.*'))
                <li class="nav-item {{ $setActive ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ $setActive ? 'active' : '' }}" aria-expanded="{{ $setActive ? 'true' : 'false' }}"><i class="nav-icon bi bi-gear"></i> <span>{{ ui('settings') }}</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ route('profile.show') }}" data-menu-text="{{ ui('profile') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}"><i class="nav-icon bi bi-person"></i> <span>{{ ui('profile') }}</span></a></li>
                        @can('feature.manage')
                        @feature('sessions')
                        <li class="nav-item"><a href="{{ route('sessions.index') }}" data-menu-text="{{ ui('sessions') }}" class="nav-link {{ request()->routeIs('sessions.*') ? 'active' : '' }}"><i class="nav-icon bi bi-pc-display"></i> <span>{{ ui('sessions') }}</span></a></li>
                        @endfeature
                        @endcan
                        @canany(['feature.manage', 'translation.view'])
                        @feature('api-tokens')
                        <li class="nav-item"><a href="{{ route('api-tokens.index') }}" data-menu-text="{{ ui('api_tokens') }}" class="nav-link {{ request()->routeIs('api-tokens.*') ? 'active' : '' }}"><i class="nav-icon bi bi-hdd-network"></i> <span>{{ ui('api_tokens') }}</span></a></li>
                        @endfeature
                        @endcanany
                        @can('feature.manage')
                        @feature('features')
                        <li class="nav-item"><a href="{{ route('features.index') }}" data-menu-text="{{ ui('features') }}" class="nav-link {{ request()->routeIs('features.*') ? 'active' : '' }}"><i class="nav-icon bi bi-toggle-on"></i> <span>{{ ui('features') }}</span></a></li>
                        @endfeature
                        @endcan
                        @can('feature.manage')
                        @feature('plans')
                        <li class="nav-item"><a href="{{ route('plans.index') }}" data-menu-text="{{ ui('plans') }}" class="nav-link {{ request()->routeIs('plans.*') ? 'active' : '' }}"><i class="nav-icon bi bi-tags"></i> <span>{{ ui('plans') }}</span></a></li>
                        @endfeature
                        @can('billing.view')
                        @feature('billing')
                        <li class="nav-item"><a href="{{ route('admin.billing.index') }}" data-menu-text="{{ ui('billing_admin') }}" class="nav-link {{ request()->routeIs('admin.billing.*') ? 'active' : '' }}"><i class="nav-icon bi bi-graph-up"></i> <span>{{ ui('billing_admin') }}</span></a></li>
                        @endfeature
                        @endcan
                        @endcan
                        @can('translation.view')
                        @feature('translations')
                        <li class="nav-item"><a href="{{ route('translations.index') }}" data-menu-text="{{ ui('translations') }}" class="nav-link {{ request()->routeIs('translations.*') ? 'active' : '' }}"><i class="nav-icon bi bi-translate"></i> <span>{{ ui('translations') }}</span></a></li>
                        @endfeature
                        @endcan
                        @can('feature.manage')
                        <li class="nav-item"><a href="{{ route('settings.system') }}" data-menu-text="{{ ui('system_settings') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"><i class="nav-icon bi bi-gear-fill"></i> <span>{{ ui('system_settings') }}</span></a></li>
                        @endcan
                    </ul>
                </li>

                @feature('billing')
                <li class="nav-item"><a href="{{ route('billing.index') }}" data-menu-text="{{ ui('billing') }}" class="nav-link {{ request()->routeIs('billing.*') ? 'active' : '' }}"><i class="nav-icon bi bi-credit-card"></i> <span>{{ ui('billing') }}</span></a></li>
                @endfeature

                <li class="nav-item">
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="nav-link border-0 bg-transparent text-start w-100"><i class="nav-icon bi bi-box-arrow-right"></i> <span>{{ ui('logout') }}</span></button>
                    </form>
                </li>

                <li class="nav-header">TEMPLATE</li>

                {{-- Widgets --}}
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-box-seam-fill"></i> <span>Widgets</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/widgets/small-box.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Small Box</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/widgets/info-box.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Info Box</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/widgets/cards.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Cards</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/widgets/social.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Social &amp; post</span></a></li>
                    </ul>
                </li>

                {{-- Layout Options --}}
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-clipboard-fill"></i> <span>Layout Options</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/unfixed-sidebar.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Default Sidebar</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/fixed-sidebar.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Fixed Sidebar</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/fixed-header.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Fixed Header</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/fixed-footer.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Fixed Footer</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/fixed-complete.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Fixed Complete</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/layout-custom-area.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Layout + Custom Area</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/sidebar-mini.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Sidebar Mini</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/collapsed-sidebar.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Sidebar Mini + Collapsed</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/collapsed-sidebar-without-hover.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Sidebar Mini + No Hover</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/logo-switch.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Sidebar Mini + Logo Switch</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/top-nav.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Top Nav + No Sidebar</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/layout/layout-rtl.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Layout RTL</span></a></li>
                    </ul>
                </li>

                {{-- UI Elements --}}
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-tree-fill"></i> <span>UI Elements</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/UI/general.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>General</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/UI/icons.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Icons</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/UI/timeline.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Timeline</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/UI/ribbons.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Ribbons</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/UI/colors.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Colors</span></a></li>
                    </ul>
                </li>

                {{-- Mailbox --}}
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-envelope"></i> <span>Mailbox</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/mailbox/inbox.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Inbox</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/mailbox/read.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Read Message</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/mailbox/compose.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Compose</span></a></li>
                    </ul>
                </li>

                {{-- Forms --}}
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-pencil-square"></i> <span>Forms</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/forms/elements.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Elements</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/forms/layout.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Layout</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/forms/validation.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Validation</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/forms/wizard.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Wizard</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/forms/advanced.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Advanced Elements</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/forms/editors.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Editors</span></a></li>
                    </ul>
                </li>

                {{-- Tables --}}
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-table"></i> <span>Tables</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/tables/simple.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Simple Tables</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/tables/data.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Data Tables</span></a></li>
                    </ul>
                </li>

                {{-- Charts --}}
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-graph-up"></i> <span>Charts</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/charts/apexcharts.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>ApexCharts</span></a></li>
                    </ul>
                </li>

                {{-- Pages --}}
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-file-earmark-text"></i> <span>Pages</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/profile.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Profile</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/settings.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Settings</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/invoice.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Invoice</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/calendar.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Calendar</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/kanban.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Kanban</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/chat.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Chat</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/file-manager.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>File Manager</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/projects.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Projects</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/gallery.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Gallery</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/search-results.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Search Results</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/pricing.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Pricing</span></a></li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/faq.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>FAQ</span></a></li>
                        <li class="nav-item">
                            <a href="#" class="nav-link"><i class="nav-icon bi bi-circle"></i> <span>Error</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/404.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-record-circle-fill"></i> <span>404</span></a></li>
                                <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/500.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-record-circle-fill"></i> <span>500</span></a></li>
                                <li class="nav-item"><a href="{{ asset('vendor/adminlte/pages/maintenance.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-record-circle-fill"></i> <span>Maintenance</span></a></li>
                            </ul>
                        </li>
                    </ul>
                </li>

                {{-- Auth examples --}}
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-box-arrow-in-right"></i> <span>Auth</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="#" class="nav-link"><i class="nav-icon bi bi-box-arrow-in-right"></i> <span>Version 1</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="{{ asset('vendor/adminlte/examples/login.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Login</span></a></li>
                                <li class="nav-item"><a href="{{ asset('vendor/adminlte/examples/register.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Register</span></a></li>
                                <li class="nav-item"><a href="{{ asset('vendor/adminlte/examples/forgot-password.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Forgot Password</span></a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link"><i class="nav-icon bi bi-box-arrow-in-right"></i> <span>Version 2</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="{{ asset('vendor/adminlte/examples/login-v2.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Login</span></a></li>
                                <li class="nav-item"><a href="{{ asset('vendor/adminlte/examples/register-v2.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Register</span></a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a href="{{ asset('vendor/adminlte/examples/lockscreen.html') }}" class="nav-link" target="_blank"><i class="nav-icon bi bi-circle"></i> <span>Lockscreen</span></a></li>
                    </ul>
                </li>

                {{-- Multi Level Example --}}
                <li class="nav-header">MULTI LEVEL EXAMPLE</li>
                <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon bi bi-circle-fill"></i> <span>Level 1</span></a></li>
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-circle-fill"></i> <span>Level 1</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon bi bi-circle"></i> <span>Level 2</span></a></li>
                        <li class="nav-item">
                            <a href="#" class="nav-link"><i class="nav-icon bi bi-circle"></i> <span>Level 2</span><i class="nav-arrow bi bi-chevron-right"></i></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon bi bi-record-circle-fill"></i> <span>Level 3</span></a></li>
                                <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon bi bi-record-circle-fill"></i> <span>Level 3</span></a></li>
                                <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon bi bi-record-circle-fill"></i> <span>Level 3</span></a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon bi bi-circle"></i> <span>Level 2</span></a></li>
                    </ul>
                </li>
                <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon bi bi-circle-fill"></i> <span>Level 1</span></a></li>

                {{-- Labels --}}
                <li class="nav-header">LABELS</li>
                <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon bi bi-circle text-danger"></i> <span>Important</span></a></li>
                <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon bi bi-circle text-warning"></i> <span>Warning</span></a></li>
                <li class="nav-item"><a href="#" class="nav-link"><i class="nav-icon bi bi-circle text-info"></i> <span>Informational</span></a></li>
            </ul>
        </nav>
    </div>
</aside>
