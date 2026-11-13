<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({});

const logout = () => {
    form.post(route('logout'));
};
</script>

<template>
    <Head title="Pending Approval" />

    <div class="min-h-screen bg-gray-100 flex flex-col justify-center items-center">
        <div class="max-w-md w-full bg-white shadow-lg rounded-lg p-8 text-center space-y-6">
            <svg class="mx-auto h-16 w-16 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h2 class="text-2xl font-bold text-gray-800">Pending Approval</h2>
            <p class="text-gray-600">
                Your account has been created successfully, but it needs to be approved by a Manager or Admin before you can access the Mess Management System.
            </p>
            <p class="text-gray-500 text-sm">
                Please check back later or contact your mess manager.
            </p>
            <div class="pt-4 border-t border-gray-200">
                <button
                    @click="logout"
                    class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                >
                    Log Out
                </button>
            </div>
        </div>
    </div>
</template>
