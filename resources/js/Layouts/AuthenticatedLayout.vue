<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav
                class="border-b border-gray-100 bg-white print:hidden"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>
                                <NavLink :href="route('meals.index')" :active="route().current('meals.*')">
                                    Meal Chart
                                </NavLink>
                                <NavLink :href="route('expenses.index')" :active="route().current('expenses.*')">
                                    Expenses
                                </NavLink>
                                <NavLink v-if="$page.props.auth.user.role === 'admin' || $page.props.auth.user.role === 'manager'" :href="route('fixed-charges.index')" :active="route().current('fixed-charges.*')">
                                    Fixed Charges
                                </NavLink>
                                <NavLink :href="route('bazar-rosters.index')" :active="route().current('bazar-rosters.*')">
                                    Roster
                                </NavLink>
                                <NavLink :href="route('deposits.index')" :active="route().current('deposits.*')">
                                    Deposits
                                </NavLink>
                                <NavLink :href="route('reports.index')" :active="route().current('reports.*')">
                                    Reports
                                </NavLink>
                                <NavLink :href="route('payables.index')" :active="route().current('payables.*')">
                                    Payments
                                </NavLink>
                                <NavLink v-if="$page.props.auth.user.role === 'admin'" :href="route('users.index')" :active="route().current('users.*')">
                                    Users
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center gap-4">

                            <!-- Active Month Badge -->
                            <div v-if="$page.props.activeMonth" class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold tracking-wide border border-emerald-200">
                                📅 {{ $page.props.activeMonth }}
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('meals.index')" :active="route().current('meals.*')">
                            Meal Chart
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('expenses.index')" :active="route().current('expenses.*')">
                            Expenses
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('bazar-rosters.index')" :active="route().current('bazar-rosters.*')">
                            Roster
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('deposits.index')" :active="route().current('deposits.*')">
                            Deposits
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('reports.index')" :active="route().current('reports.*')">
                            Reports
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('payables.index')" :active="route().current('payables.*')">
                            Payments
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="$page.props.auth.user.role === 'admin'" :href="route('users.index')" :active="route().current('users.*')">
                            Users
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                            <div v-if="$page.props.activeMonth" class="mt-2 inline-block px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded text-xs font-bold border border-emerald-200">
                                📅 Active: {{ $page.props.activeMonth }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-white shadow print:hidden"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Flash Alerts -->
            <div v-if="$page.props.flash?.success" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                <div class="flex items-center justify-between rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-base">✅</span>
                        <span class="font-medium">{{ $page.props.flash.success }}</span>
                    </div>
                    <button @click="$page.props.flash.success = null" class="text-emerald-500 hover:text-emerald-700 font-bold ml-4">✕</button>
                </div>
            </div>

            <div v-if="$page.props.flash?.error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                <div class="flex items-center justify-between rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-base">⚠️</span>
                        <span class="font-medium">{{ $page.props.flash.error }}</span>
                    </div>
                    <button @click="$page.props.flash.error = null" class="text-rose-500 hover:text-rose-700 font-bold ml-4">✕</button>
                </div>
            </div>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
