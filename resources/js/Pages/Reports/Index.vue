<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

defineProps({
    sessions: Array
});

const page = usePage();
const canManage = ['admin', 'manager'].includes(page.props.auth.user.role);

const reopen = (session) => {
    if (confirm(`Wake up "${session.name}"? This will close any other active month.`)) {
        router.post(route('month-sessions.reopen', session.id), { _method: 'patch' });
    }
};
</script>

<template>
    <Head title="Reports" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Month Reports</h2>
        </template>

        <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div v-if="sessions.length === 0" class="p-8 text-center text-gray-500">
                    No month sessions found.
                </div>
                <div v-else class="divide-y">
                    <div v-for="session in sessions" :key="session.id" class="p-4 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                                {{ session.name }}
                                <span v-if="session.is_active" class="bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded-full">Active</span>
                                <span v-else class="bg-gray-200 text-gray-700 text-xs px-2 py-0.5 rounded-full">Closed</span>
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">{{ session.start_date.slice(0,10) }} to {{ session.end_date.slice(0,10) }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button v-if="canManage && !session.is_active" @click="reopen(session)" class="px-4 py-2 bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 rounded-lg text-sm font-medium transition">
                                Wake Up (Re-open)
                            </button>
                            <Link :href="route('reports.show', session.id)" class="px-4 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-sm font-medium transition">
                                View Report &rarr;
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
