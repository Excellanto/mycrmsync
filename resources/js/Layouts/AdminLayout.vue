<template>
	<div class="min-h-screen flex bg-app">
		<aside
			class="sticky top-0 flex h-screen shrink-0 flex-col border-r border-slate-200/90 bg-slate-50 transition-[width] duration-200 ease-out"
			:class="sidebarCollapsed ? 'w-[4.25rem]' : 'w-64'"
		>
			<div
				class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-200/80 px-3 py-3"
				:class="sidebarCollapsed ? 'flex-col gap-3' : ''"
			>
				<span
					v-show="!sidebarCollapsed"
					class="truncate text-lg font-semibold tracking-tight text-slate-800"
				>
					Admin
				</span>
				<span
					v-show="sidebarCollapsed"
					class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white shadow-sm"
					aria-hidden="true"
				>
					A
				</span>
				<button
					type="button"
					class="rounded-full p-1.5 text-slate-500 transition hover:bg-slate-200/90 hover:text-slate-800"
					:title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
					:aria-expanded="!sidebarCollapsed"
					aria-controls="admin-sidebar-nav"
					@click="sidebarCollapsed = !sidebarCollapsed"
				>
					<svg
						class="h-5 w-5 transition-transform duration-200"
						:class="sidebarCollapsed ? 'rotate-180' : ''"
						fill="none"
						viewBox="0 0 24 24"
						stroke="currentColor"
						aria-hidden="true"
					>
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
					</svg>
				</button>
			</div>

			<nav
				id="admin-sidebar-nav"
				class="min-h-0 flex-1 space-y-1 overflow-y-auto overflow-x-hidden px-2 py-3"
			>
				<Link
					:href="route('admin.dashboard')"
					:class="navRowClass(isDashboardActive)"
					:title="sidebarCollapsed ? 'Dashboard' : undefined"
				>
					<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md" :class="iconWrapClass(isDashboardActive)">
						<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
							<path
								stroke-linecap="round"
								stroke-linejoin="round"
								stroke-width="2"
								d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
							/>
						</svg>
					</span>
					<span v-show="!sidebarCollapsed" class="truncate">Dashboard</span>
				</Link>

				<Link
					v-if="showCallLogsNav"
					:href="route('admin.call-logs.index')"
					:class="navRowClass(isPathActive('/admin/call-logs'))"
					:title="sidebarCollapsed ? 'Call Log Management' : undefined"
				>
					<span
						class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
						:class="iconWrapClass(isPathActive('/admin/call-logs'))"
					>
						<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
							<path
								stroke-linecap="round"
								stroke-linejoin="round"
								stroke-width="2"
								d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
							/>
						</svg>
					</span>
					<span v-show="!sidebarCollapsed" class="truncate">Call Log Management</span>
				</Link>

				<Link
					v-if="showContactManagementNav"
					:href="route('admin.contacts.index')"
					:class="navRowClass(isPathActive('/admin/contacts'))"
					:title="sidebarCollapsed ? 'Contact Management' : undefined"
				>
					<span
						class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
						:class="iconWrapClass(isPathActive('/admin/contacts'))"
					>
						<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
							<path
								stroke-linecap="round"
								stroke-linejoin="round"
								stroke-width="2"
								d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
							/>
						</svg>
					</span>
					<span v-show="!sidebarCollapsed" class="truncate">Contact Management</span>
				</Link>

				<Link
					v-if="showUrlManagementNav"
					:href="route('admin.url-management.index')"
					:class="navRowClass(isPathActive('/admin/url-management'))"
					:title="sidebarCollapsed ? 'Url Management' : undefined"
				>
					<span
						class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
						:class="iconWrapClass(isPathActive('/admin/url-management'))"
					>
						<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
							<path
								stroke-linecap="round"
								stroke-linejoin="round"
								stroke-width="2"
								d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
							/>
						</svg>
					</span>
					<span v-show="!sidebarCollapsed" class="truncate">Url Management</span>
				</Link>

				<div v-if="showApiManagementModule" :class="sidebarCollapsed ? 'mt-1' : 'mt-2'">
					<button
						type="button"
						:class="[
							navRowClass(isApiManagementSectionActive),
							sidebarCollapsed ? 'justify-center' : 'justify-between',
						]"
						:title="sidebarCollapsed ? 'Api Management' : undefined"
						@click="apiManagementOpen = !apiManagementOpen"
						aria-controls="api-management-sub"
						:aria-expanded="apiManagementOpen ? 'true' : 'false'"
					>
						<span class="flex min-w-0 items-center gap-3">
							<span
								class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
								:class="iconWrapClass(isApiManagementSectionActive)"
							>
								<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
									<path
										stroke-linecap="round"
										stroke-linejoin="round"
										stroke-width="2"
										d="M8 9h8m-8 4h8m-7 8h6a2 2 0 002-2V7a2 2 0 00-2-2h-1l-1-1H9L8 5H7a2 2 0 00-2 2v12a2 2 0 002 2h1"
									/>
								</svg>
							</span>
							<span v-show="!sidebarCollapsed" class="truncate text-left">Api Management</span>
						</span>
						<svg
							v-show="!sidebarCollapsed"
							class="h-4 w-4 shrink-0 opacity-70 transition-transform"
							:class="apiManagementOpen ? 'rotate-180' : 'rotate-0'"
							fill="none"
							viewBox="0 0 24 24"
							stroke="currentColor"
							aria-hidden="true"
						>
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
						</svg>
					</button>
					<div
						v-show="apiManagementOpen"
						id="api-management-sub"
						:class="
							sidebarCollapsed
								? 'flex flex-col items-center gap-0.5 pt-1'
								: 'ml-2 space-y-0.5 border-l border-slate-200/90 pl-3 pt-1'
						"
					>
						<Link
							v-if="showApiEndpointMapperNav"
							:href="route('admin.api-endpoint-mapper.index')"
							:class="subNavClass(isPathActive('/admin/api-endpoint-mapper'), sidebarCollapsed)"
							title="API Endpoint Mapper"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M4 6h16M4 12h16M4 18h7"
								/>
							</svg>
							<span v-show="!sidebarCollapsed">API Endpoint Mapper</span>
						</Link>
						<Link
							v-if="showMappedApisNav"
							:href="route('admin.mapped-apis.index')"
							:class="subNavClass(isPathActive('/admin/mapped-apis'), sidebarCollapsed)"
							title="Mapped APIs"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"
								/>
							</svg>
							<span v-show="!sidebarCollapsed">Mapped APIs</span>
						</Link>
					</div>
				</div>

				<div :class="sidebarCollapsed ? 'mt-1' : 'mt-2'">
					<button
						type="button"
						:class="[
							navRowClass(isUserMgmtSectionActive),
							sidebarCollapsed ? 'justify-center' : 'justify-between',
						]"
						:title="sidebarCollapsed ? 'User Management' : undefined"
						@click="userMgmtOpen = !userMgmtOpen"
						aria-controls="user-mgmt-sub"
						:aria-expanded="userMgmtOpen ? 'true' : 'false'"
					>
						<span class="flex min-w-0 items-center gap-3">
							<span
								class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
								:class="iconWrapClass(isUserMgmtSectionActive)"
							>
								<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
									<path
										stroke-linecap="round"
										stroke-linejoin="round"
										stroke-width="2"
										d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
									/>
								</svg>
							</span>
							<span v-show="!sidebarCollapsed" class="truncate text-left">User Management</span>
						</span>
						<svg
							v-show="!sidebarCollapsed"
							class="h-4 w-4 shrink-0 opacity-70 transition-transform"
							:class="userMgmtOpen ? 'rotate-180' : 'rotate-0'"
							fill="none"
							viewBox="0 0 24 24"
							stroke="currentColor"
							aria-hidden="true"
						>
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
						</svg>
					</button>
					<div
						v-show="userMgmtOpen"
						id="user-mgmt-sub"
						:class="
							sidebarCollapsed
								? 'flex flex-col items-center gap-0.5 pt-1'
								: 'ml-2 space-y-0.5 border-l border-slate-200/90 pl-3 pt-1'
						"
					>
						<Link
							v-if="showUsersNav"
							:href="route('admin.users.index')"
							:class="subNavClass(isPathActive('/admin/users'), sidebarCollapsed)"
							title="Users"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
							</svg>
							<span v-show="!sidebarCollapsed">Users</span>
						</Link>
						<Link
							v-if="showAssignmentRulesNav"
							:href="route('admin.role-assignment-rules.index')"
							:class="subNavClass(isPathActive('/admin/role-assignment-rules'), sidebarCollapsed)"
							title="Role assignment rules"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
								/>
							</svg>
							<span v-show="!sidebarCollapsed">Assignment rules</span>
						</Link>
						<Link
							v-if="showTenantsNav"
							:href="tenantsNavHref"
							:class="subNavClass(isPathActive('/admin/tenants'), sidebarCollapsed)"
							:title="tenantsNavTitle"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
								/>
							</svg>
							<span v-show="!sidebarCollapsed">{{ tenantsNavTitle }}</span>
						</Link>
						<Link
							v-if="showRolesNav"
							:href="route('admin.roles.index')"
							:class="subNavClass(isPathActive('/admin/roles'), sidebarCollapsed)"
							title="Roles"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
								/>
							</svg>
							<span v-show="!sidebarCollapsed">Roles</span>
						</Link>
						<Link
							v-if="showPermissionsNav"
							:href="route('admin.permissions.index')"
							:class="subNavClass(isPathActive('/admin/permissions'), sidebarCollapsed)"
							title="Permissions"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
								/>
							</svg>
							<span v-show="!sidebarCollapsed">Permissions</span>
						</Link>
						<Link
							v-if="showActivityLogsNav"
							:href="route('admin.activity-logs.index')"
							:class="subNavClass(isPathActive('/admin/activity-logs'), sidebarCollapsed)"
							title="Activity Logs"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
								/>
							</svg>
							<span v-show="!sidebarCollapsed">Activity Logs</span>
						</Link>
					</div>
				</div>

				<!-- Configurations / Email management removed -->

				<div v-if="showSettingsModule" :class="sidebarCollapsed ? 'mt-1' : 'mt-2'">
					<button
						type="button"
						:class="[
							navRowClass(isSettingsSectionActive),
							sidebarCollapsed ? 'justify-center' : 'justify-between',
						]"
						:title="sidebarCollapsed ? 'Settings' : undefined"
						@click="settingsOpen = !settingsOpen"
						aria-controls="settings-sub"
						:aria-expanded="settingsOpen ? 'true' : 'false'"
					>
						<span class="flex min-w-0 items-center gap-3">
							<span
								class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
								:class="iconWrapClass(isSettingsSectionActive)"
							>
								<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
									<path
										stroke-linecap="round"
										stroke-linejoin="round"
										stroke-width="2"
										d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
									/>
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
								</svg>
							</span>
							<span v-show="!sidebarCollapsed" class="truncate text-left">Settings</span>
						</span>
						<svg
							v-show="!sidebarCollapsed"
							class="h-4 w-4 shrink-0 opacity-70 transition-transform"
							:class="settingsOpen ? 'rotate-180' : 'rotate-0'"
							fill="none"
							viewBox="0 0 24 24"
							stroke="currentColor"
							aria-hidden="true"
						>
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
						</svg>
					</button>
					<div
						v-show="settingsOpen"
						id="settings-sub"
						:class="
							sidebarCollapsed
								? 'flex flex-col items-center gap-0.5 pt-1'
								: 'ml-2 space-y-0.5 border-l border-slate-200/90 pl-3 pt-1'
						"
					>
						<Link
							:href="route('admin.languages.index')"
							:class="subNavClass(isPathActive('/admin/languages'), sidebarCollapsed)"
							title="Languages"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"
								/>
							</svg>
							<span v-show="!sidebarCollapsed">Languages</span>
						</Link>
						<!-- Removed AI Settings and Pricing -->
						<Link
							v-if="showDataConfigurationNav"
							:href="route('admin.data-configuration.index')"
							:class="subNavClass(isPathActive('/admin/data-configuration'), sidebarCollapsed)"
							title="Data Configuration"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M4 6h16M4 12h16M4 18h7"
								/>
							</svg>
							<span v-show="!sidebarCollapsed">Data Configuration</span>
						</Link>
						<Link
							v-if="showIntegrationsNav"
							:href="route('admin.integrations.index')"
							:class="subNavClass(isPathActive('/admin/integrations'), sidebarCollapsed)"
							title="Integrations"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M13 10V3L4 14h7v7l9-11h-7z"
								/>
							</svg>
							<span v-show="!sidebarCollapsed">Integrations</span>
						</Link>
						<Link
							v-if="showSystemSettingsNav"
							:href="route('admin.settings.index')"
							:class="subNavClass(isPathActive('/admin/settings'), sidebarCollapsed)"
							title="System settings"
						>
							<svg class="h-4 w-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
								/>
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
							</svg>
							<span v-show="!sidebarCollapsed">System settings</span>
						</Link>
					</div>
				</div>
			</nav>
		</aside>

		<div class="flex-1 flex flex-col bg-app min-h-0 min-w-0">
			<header class="bg-white border-b border-gray-200 shrink-0">
				<div class="w-full px-4 py-3 flex items-center justify-between">
					<div class="flex items-center gap-6">
						<h1 class="text-lg font-semibold">
							<slot name="title">Admin</slot>
						</h1>
					</div>
					<div class="ml-auto relative" ref="profileMenuRoot">
						<button
							type="button"
							class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-gray-100"
							@click="profileOpen = !profileOpen"
						>
							<span class="hidden sm:block text-sm text-gray-700">{{ user?.name || 'Account' }}</span>
							<span
								class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold text-gray-700"
								aria-hidden="true"
							>
								{{ userInitials }}
							</span>
							<svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
							</svg>
						</button>
						<div
							v-if="profileOpen"
							class="absolute right-0 z-50 mt-2 w-48 rounded-xl bg-white py-1 shadow-lg ring-1 ring-black/5"
						>
							<Link href="/account/settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
								>Account Settings</Link
							>
							<Link
								:href="route('admin.profile.edit')"
								class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
							>
								Edit Profile
							</Link>
							<hr class="my-1" />
							<Link
								href="/logout"
								method="post"
								as="button"
								class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
							>
								Logout
							</Link>
						</div>
					</div>
				</div>
			</header>
			<main class="w-full flex-1 min-h-0 overflow-auto p-6">
				<slot />
			</main>
			<PToast position="top-right" />
		</div>
	</div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const currentUrl = computed(() => page.url || window.location.pathname || '/');
const isActivePath = (path) => currentUrl.value.startsWith(path);

/** Normalized path without query/trailing slash — for precise active states */
const navPath = computed(() => {
	const raw = (currentUrl.value || '').split('?')[0] || '/';
	return raw.replace(/\/$/, '') || '/';
});

function pathMatchesPrefix(prefix) {
	const p = navPath.value;
	return p === prefix || p.startsWith(prefix + '/');
}

/** Used by settings / user sub-links */
function isPathActive(prefix) {
	return pathMatchesPrefix(prefix);
}

const sidebarCollapsed = ref(false);

const isDashboardActive = computed(() => navPath.value === '/admin');
const isApiManagementSectionActive = computed(
	() => pathMatchesPrefix('/admin/api-endpoint-mapper') || pathMatchesPrefix('/admin/mapped-apis')
);

const contactManagementAvailable = computed(
	() => page.props.auth?.contact_management_available === true
);

const isUserMgmtSectionActive = computed(
	() =>
		pathMatchesPrefix('/admin/users') ||
		pathMatchesPrefix('/admin/tenants') ||
		pathMatchesPrefix('/admin/roles') ||
		pathMatchesPrefix('/admin/permissions') ||
		pathMatchesPrefix('/admin/activity-logs') ||
		pathMatchesPrefix('/admin/role-assignment-rules')
);

const isSettingsSectionActive = computed(
	() =>
		pathMatchesPrefix('/admin/languages') ||
		pathMatchesPrefix('/admin/data-configuration') ||
		pathMatchesPrefix('/admin/integrations') ||
		pathMatchesPrefix('/admin/settings')
);

const isConfigurationsSectionActive = computed(() => pathMatchesPrefix('/admin/configurations'));

function navRowClass(active) {
	return [
		'flex w-full min-w-0 items-center gap-3 rounded-lg px-2 py-2.5 text-sm font-medium transition-colors outline-none',
		active ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100/90',
	];
}

function iconWrapClass(active) {
	return active ? 'bg-white/15 text-white' : 'bg-slate-200/70 text-slate-700';
}

function subNavClass(active, rail = false) {
	return [
		'flex items-center rounded-md text-sm transition-colors',
		rail ? 'min-w-0 justify-center gap-0 px-2 py-2' : 'gap-2 px-2 py-1.5',
		active ? 'bg-blue-600 font-medium text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100',
	];
}

const userMgmtOpen = ref(false);
const apiManagementOpen = ref(false);
const configurationsOpen = ref(false);
const settingsOpen = ref(false);

const user = computed(() => page.props.auth?.user || null);
const permissionNames = computed(() => page.props.auth?.permissions || []);
/**
 * Laravel Gate / policy results (same as route authorization). Prefer this over relying only on the
 * `permissions` array: once a role has *any* `nav.*`, the sidebar drops legacy fallbacks (`users.view`, …),
 * so you can have `users.view` but only `nav.user-management.tenants.show` enabled — Tenants appeared,
 * Users/Roles did not. These flags mirror `$user->can(...)`, including Gate::before for platform masters.
 */
const navUserMgmtCan = computed(() => page.props.auth?.can?.user_management ?? {});
/**
 * Sidebar visibility bypass for platform-scoped / Super Admin — uses server {@see HandleInertiaRequests} flags
 * plus resilient fallbacks (role slug / name matching).
 */

function userMatchesSuperAdminNav(u) {
	if (!u) return false;
	if (Boolean(u.super_admin_nav)) return true;
	const slugs = u.role_slugs;
	if (Array.isArray(slugs) && slugs.includes('super_admin')) return true;
	if (!u.roles?.length) return false;
	return u.roles.some((r) => {
		const n = String(r).trim().toLowerCase().replace(/\s+/g, ' ');
		return n === 'super admin' || n === 'super_admin' || n === 'superadmin';
	});
}

const bypassNavGranular = computed(() => userMatchesSuperAdminNav(user.value));
const hasAnyNavPermission = computed(() => permissionNames.value.some((name) => name.startsWith('nav.')));
// When true, show every sidebar group and submenu for the current user (Super Admin / platform master).
const showAllMenus = computed(() => bypassNavGranular.value);

/**
 * Nav visibility: when the role has any `nav.*` permission, each nav item is driven only by
 * explicit nav permissions (or legacy capability fallback when no nav.* exists).
 * This prevents hiding a nav checkbox in Roles while still showing the link because of e.g. users.view.
 */
function canShowNav(navPermission, fallbackPermission) {
	if (bypassNavGranular.value) return true;
	// Accept both `nav.*` style and legacy permission keys without the `nav.` prefix
	if (permissionNames.value.includes(navPermission)) return true;
	const alt = navPermission.replace(/^nav\./, '');
	if (permissionNames.value.includes(alt)) return true;
	if (!hasAnyNavPermission.value) {
		if (fallbackPermission && permissionNames.value.includes(fallbackPermission)) return true;
		if (!fallbackPermission) return true;
		return false;
	}
	return false;
}

/**
 * User Management section: show if parent nav is on, or any sub-nav is on (so child-only roles still work),
 * or legacy installs with users.view but no nav.* at all.
 * Settings stays strict (parent-only) in navSettingsParentAllowed.
 */
const navUserMgmtParentAllowed = computed(() => {
	if (showAllMenus.value) return true;
	if (bypassNavGranular.value) return true;
	// Legacy fallback: if the installation doesn't use any nav.* permissions, keep old behavior.
	if (!hasAnyNavPermission.value) return permissionNames.value.includes('users.view');
	// Otherwise require explicit parent "Show in Navigation" permission.
	// Support both `nav.user-management.show` and legacy `user-management.show-in-sidebar`.
	if (permissionNames.value.includes('nav.user-management.show')) return true;
	if (permissionNames.value.includes('user-management.show-in-sidebar')) return true;
	return false;
});
const showUsersNav = computed(() => {
	if (showAllMenus.value) return true;
	if (!navUserMgmtParentAllowed.value) return false;
	// Require explicit nav permission when nav.* permissions are used; legacy fallback to users.view when none exist
	return canShowNav('nav.user-management.users.show', 'users.view');
});
const showAssignmentRulesNav = computed(() => {
	if (showAllMenus.value) return true;
	if (!navUserMgmtParentAllowed.value) return false;
	return canShowNav('nav.user-management.assignment-rules.show', 'users.view');
});
const showRolesNav = computed(() => {
	if (showAllMenus.value) return true;
	if (!navUserMgmtParentAllowed.value) return false;
	return canShowNav('nav.user-management.roles.show', 'roles.view');
});
const showPermissionsNav = computed(() => {
	if (showAllMenus.value) return true;
	if (!navUserMgmtParentAllowed.value) return false;
	return canShowNav('nav.user-management.permissions.show', 'permissions.view');
});
const showActivityLogsNav = computed(() => {
	if (showAllMenus.value) return true;
	if (!navUserMgmtParentAllowed.value) return false;
	return canShowNav('nav.user-management.activity-logs.show', 'activity-logs.view');
});
const showUrlManagementNav = computed(() => {
	if (showAllMenus.value) return true;
	return canShowNav('nav.url-management.show', 'short-urls.view');
});
const showTenantsNav = computed(() => {
	if (showAllMenus.value) return true;
	if (!navUserMgmtParentAllowed.value) return false;
	return canShowNav('nav.user-management.tenants.show', 'tenants.view');
});
const tenantsNavHref = computed(() => {
	if (user.value?.is_master) {
		return route('admin.tenants.index');
	}
	if (user.value?.tenant_id) {
		return route('admin.tenants.edit', user.value.tenant_id);
	}
	return route('admin.tenants.index');
});
const tenantsNavTitle = computed(() => (user.value?.is_master ? 'Tenants' : 'Company'));
const showCallLogsNav = computed(() => {
	if (showAllMenus.value) return true;
	return canShowNav('nav.crm.call-logs.show', 'call-logs.view');
});

const showContactManagementNav = computed(() => {
	if (!contactManagementAvailable.value) return false;
	if (showAllMenus.value) return true;
	if (canShowNav('nav.contact-management.show', 'contacts.view')) return true;
	return canShowNav('nav.contact-management.contacts.show', 'contacts.view');
});

const showUserMgmtModule = computed(
	() =>
		showUsersNav.value ||
		showAssignmentRulesNav.value ||
		showRolesNav.value ||
		showPermissionsNav.value ||
		showActivityLogsNav.value ||
		showTenantsNav.value
);

const navApiManagementParentAllowed = computed(() => {
	if (showAllMenus.value) return true;
	if (bypassNavGranular.value) return true;
	if (!hasAnyNavPermission.value) {
		return permissionNames.value.includes('admin-panel-access');
	}
	return permissionNames.value.includes('nav.api-management.show');
});
const showApiEndpointMapperNav = computed(() => {
	if (showAllMenus.value) return true;
	if (!navApiManagementParentAllowed.value) return false;
	return canShowNav('nav.api-management.api-endpoint-mapper.show', null);
});
const showMappedApisNav = computed(() => {
	if (showAllMenus.value) return true;
	if (!navApiManagementParentAllowed.value) return false;
	return canShowNav('nav.api-management.mapped-apis.show', null);
});
const showApiManagementModule = computed(
	() => showApiEndpointMapperNav.value || showMappedApisNav.value
);

const navSettingsParentAllowed = computed(() => {
	if (showAllMenus.value) return true;
	if (bypassNavGranular.value) return true;
	// Legacy fallback: if no nav.* permissions exist, allow settings based on legacy 'settings.view'
	if (!hasAnyNavPermission.value) return permissionNames.value.includes('settings.view');
	return permissionNames.value.includes('nav.settings.show');
});
const showLanguagesNav = computed(() => {
	if (showAllMenus.value) return true;
	if (bypassNavGranular.value) return true;
	// Parent must allow settings navigation for children to appear
	if (!navSettingsParentAllowed.value) return false;
	return canShowNav('nav.settings.languages.show', 'languages.view');
});
// AI and Pricing navs removed
const showPoolAllocationNav = computed(() => {
	if (showAllMenus.value) return true;
	if (bypassNavGranular.value) return true;
	// Parent must allow settings navigation for children to appear
	if (!navSettingsParentAllowed.value) return false;
	return canShowNav('nav.settings.pool_allocation.show', 'settings.view');
});
const showDataConfigurationNav = computed(() => {
	if (showAllMenus.value) return true;
	if (bypassNavGranular.value) return true;
	// Parent must allow settings navigation for children to appear
	if (!navSettingsParentAllowed.value) return false;
	return canShowNav('nav.settings.data-configuration.show', 'settings.view');
});
const showIntegrationsNav = computed(() => {
	if (showAllMenus.value) return true;
	if (bypassNavGranular.value) return true;
	if (!navSettingsParentAllowed.value) return false;
	return canShowNav('nav.settings.integrations.show', 'settings.view');
});
const showSystemSettingsNav = computed(() => {
	if (showAllMenus.value) return true;
	if (bypassNavGranular.value) return true;
	// Parent must allow settings navigation for children to appear
	if (!navSettingsParentAllowed.value) return false;
	return canShowNav('nav.settings.system-settings.show', 'settings.view');
});
const showSettingsModule = computed(
	() =>
		showLanguagesNav.value ||
		showPoolAllocationNav.value ||
		showDataConfigurationNav.value ||
		showIntegrationsNav.value ||
		showSystemSettingsNav.value
);

const navConfigurationsParentAllowed = computed(() => {
	if (showAllMenus.value) return true;
	if (bypassNavGranular.value) return true;
	if (!hasAnyNavPermission.value) return permissionNames.value.includes('email-accounts.view');
	return permissionNames.value.includes('nav.configurations.show');
});
// Email management removed
const showEmailManagementNav = computed(() => false);
const showConfigurationsModule = computed(() => showEmailManagementNav.value);

watch(
	bypassNavGranular,
	(master) => {
		if (!master) return;
		if (showUserMgmtModule.value) userMgmtOpen.value = true;
		if (showApiManagementModule.value) apiManagementOpen.value = true;
		if (showConfigurationsModule.value) configurationsOpen.value = true;
		if (showSettingsModule.value) settingsOpen.value = true;
	},
	{ immediate: true }
);

const userInitials = computed(() => {
	const name = user.value?.name || 'U';
	return name
		.split(' ')
		.map((p) => p.charAt(0))
		.join('')
		.slice(0, 2)
		.toUpperCase();
});

const profileOpen = ref(false);
const profileMenuRoot = ref(null);

function onBodyClick(e) {
	const root = profileMenuRoot.value;
	if (root && !root.contains(e.target)) {
		profileOpen.value = false;
	}
}

onMounted(() => {
	document.addEventListener('click', onBodyClick);

	if (
		isActivePath('/admin/api-endpoint-mapper') ||
		isActivePath('/admin/mapped-apis')
	) {
		apiManagementOpen.value = true;
	}
	if (
		isActivePath('/admin/users') ||
		isActivePath('/admin/roles') ||
		isActivePath('/admin/permissions') ||
		isActivePath('/admin/activity-logs') ||
		isActivePath('/admin/tenants')
	) {
		userMgmtOpen.value = true;
	}
	if (
		isActivePath('/admin/languages') ||
		isActivePath('/admin/settings') ||
		isActivePath('/admin/data-configuration') ||
		isActivePath('/admin/integrations')
	) {
		settingsOpen.value = true;
	}
	if (isActivePath('/admin/settings/pool-allocation')) {
		settingsOpen.value = true;
	}
	if (isActivePath('/admin/configurations')) {
		configurationsOpen.value = true;
	}
});

onBeforeUnmount(() => {
	document.removeEventListener('click', onBodyClick);
});
</script>
