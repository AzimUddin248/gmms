<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    activeSession: { type: Object,  default: null },
    users:         { type: Array,   default: () => [] },
    deposits:      { type: Array,   default: () => [] },
    canManage:     { type: Boolean, default: false },
});

const page = usePage();

// ─── Add Deposit Form ─────────────────────────────────────────────────────────
const showForm = ref(false);

const form = useForm({
    month_session_id: props.activeSession?.id ?? '',
    user_id:          page.props.auth?.user?.id ?? '',
    date:             new Date().toISOString().slice(0, 10),
    amount:           '',
    description:      '',
});

const submit = () => {
    if (!props.canManage) {
        form.user_id = page.props.auth?.user?.id;
    }
    form.month_session_id = props.activeSession?.id ?? '';
    form.post(route('deposits.store'), {
        onSuccess: () => {
            form.reset('amount', 'description');
            showForm.value = false;
        },
    });
};

const editingId = ref(null);
const editForm = useForm({
    _method: 'patch',
    date: '',
    amount: '',
    description: '',
});

const startEdit = (d) => {
    editingId.value = d.id;
    editForm.date = d.date?.slice(0, 10) || '';
    editForm.amount = d.amount;
    editForm.description = d.description || '';
};

const submitEdit = () => {
    editForm.post(route('deposits.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; }
    });
};

// ─── Approve / Reject / Delete ────────────────────────────────────────────────
const approve = (id) => router.post(route('deposits.approve', id), { _method: 'patch' }, { preserveScroll: true });
const reject  = (id) => router.post(route('deposits.reject', id), { _method: 'patch' }, { preserveScroll: true });
const destroy = (id) => {
    if (confirm('Delete this deposit?')) {
        router.post(route('deposits.destroy', id), { _method: 'delete' }, { preserveScroll: true });
    }
};

// ─── Filter & Sort Logic ──────────────────────────────────────────────────────
const filterUser = ref('');
const searchQuery = ref('');

const sortKey = ref('date');
const sortOrder = ref('desc');

const sortBy = (key) => {
    if (sortKey.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortOrder.value = 'asc';
    }
};

const filteredDeposits = computed(() => {
    let result = props.deposits.filter(d => {
        if (filterUser.value && d.user_id !== filterUser.value) return false;
        
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const dateMatch = d.date?.toLowerCase().includes(q);
            const userMatch = d.user?.name?.toLowerCase().includes(q);
            const descMatch = d.description?.toLowerCase().includes(q);
            const amountMatch = String(d.amount).includes(q);
            
            if (!dateMatch && !userMatch && !descMatch && !amountMatch) return false;
        }

        return true;
    });

    result.sort((a, b) => {
        let valA = a[sortKey.value];
        let valB = b[sortKey.value];

        if (sortKey.value === 'amount') {
            valA = parseFloat(valA);
            valB = parseFloat(valB);
        } else if (sortKey.value === 'user_name') {
            valA = a.user?.name || '';
            valB = b.user?.name || '';
        }

        if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
        if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
        return 0;
    });

    return result;
});

// ─── Summary ──────────────────────────────────────────────────────────────────
const approvedDeposits = computed(() => props.deposits.filter(d => d.status === 'approved'));

const totalApproved = computed(() =>
    approvedDeposits.value.reduce((s, d) => s + parseFloat(d.amount), 0)
);

// Per-member totals
const perMemberTotals = computed(() => {
    const map = {};
    for (const u of props.users) {
        map[u.id] = {
            name:  u.name,
            total: approvedDeposits.value
                .filter(d => d.user_id === u.id)
                .reduce((s, d) => s + parseFloat(d.amount), 0),
        };
    }
    return Object.values(map).filter(m => m.total > 0);
});

const statusBadge = (status) => {
    if (status === 'approved') return 'bg-green-100 text-green-700 ring-green-200';
    if (status === 'rejected') return 'bg-red-100 text-red-700 ring-red-200';
    return 'bg-amber-100 text-amber-700 ring-amber-200';
};
</script>

<template>
    <Head title="Deposits" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cash Deposits (জমা)</h2>
                <button v-if="activeSession"
                    @click="showForm = !showForm"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition">
                    {{ showForm ? '✕ Cancel' : '+ Add Deposit' }}
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- No active session -->
                <div v-if="!activeSession" class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                    <p class="text-gray-500">No active month session. Start a month from the Dashboard first.</p>
                </div>

                <template v-else>

                    <!-- ─── Summary ──────────────────────────────────────────── -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Grand total -->
                        <div class="rounded-xl bg-blue-600 p-5 text-white flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium opacity-80">Total Deposits Received</p>
                                <p class="text-xs opacity-60 mt-0.5">Approved only</p>
                            </div>
                            <p class="text-3xl font-bold">৳ {{ totalApproved.toFixed(2) }}</p>
                        </div>
                        <!-- Per-member breakdown -->
                        <div class="rounded-xl bg-white border border-gray-200 p-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Per Member</p>
                            <div v-if="perMemberTotals.length === 0" class="text-sm text-gray-400">No deposits yet.</div>
                            <div v-else class="space-y-1.5">
                                <div v-for="m in perMemberTotals" :key="m.name"
                                    class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-gray-700">{{ m.name }}</span>
                                    <span class="font-semibold text-blue-700">৳ {{ m.total.toFixed(2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ─── Add Deposit Form ─────────────────────────────────── -->
                    <div v-if="showForm" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <h3 class="text-base font-semibold text-gray-800 mb-4">Record New Deposit</h3>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Member -->
                            <div v-if="canManage">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Member</label>
                                <select v-model="form.user_id" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="" disabled>Select member</option>
                                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                                <p v-if="form.errors.user_id" class="mt-1 text-xs text-red-600">{{ form.errors.user_id }}</p>
                            </div>

                            <!-- Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                                <input v-model="form.date" type="date" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                <p v-if="form.errors.date" class="mt-1 text-xs text-red-600">{{ form.errors.date }}</p>
                            </div>

                            <!-- Amount -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amount (৳)</label>
                                <input v-model="form.amount" type="number" step="0.01" placeholder="0.00" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <input v-model="form.description" type="text" placeholder="e.g. Cash, Carry Forward..."
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                            </div>

                            <!-- Submit -->
                            <div class="flex items-end">
                                <button type="submit" :disabled="form.processing"
                                    class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50 transition">
                                    {{ form.processing ? 'Saving...' : 'Add Deposit' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ─── Deposit List ─────────────────────────────────────── -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <h3 class="font-semibold text-gray-800">All Deposits — {{ activeSession.name }}</h3>
                            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                                <input v-model="searchQuery" type="text" placeholder="Search..." class="text-sm rounded-lg border-gray-300 w-full sm:w-48">
                                <select v-model="filterUser" class="text-sm rounded-lg border-gray-300">
                                    <option value="">All Members</option>
                                    <option v-for="u in users" :value="u.id" :key="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="filteredDeposits.length === 0" class="px-6 py-10 text-center text-gray-400 text-sm">
                            No deposits match criteria.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th @click="sortBy('date')" class="px-4 py-3 text-left font-semibold text-gray-600 cursor-pointer hover:bg-gray-100 select-none">
                                            Date <span v-if="sortKey === 'date'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                        </th>
                                        <th @click="sortBy('user_name')" class="px-4 py-3 text-left font-semibold text-gray-600 cursor-pointer hover:bg-gray-100 select-none">
                                            Member <span v-if="sortKey === 'user_name'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                        </th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Description</th>
                                        <th @click="sortBy('amount')" class="px-4 py-3 text-right font-semibold text-gray-600 cursor-pointer hover:bg-gray-100 select-none">
                                            Amount <span v-if="sortKey === 'amount'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                        </th>
                                        <th @click="sortBy('status')" class="px-4 py-3 text-center font-semibold text-gray-600 cursor-pointer hover:bg-gray-100 select-none">
                                            Status <span v-if="sortKey === 'status'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                        </th>
                                        <th v-if="canManage" class="px-4 py-3 text-center font-semibold text-gray-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template v-for="deposit in filteredDeposits" :key="deposit.id">
                                        <tr v-if="editingId === deposit.id" class="bg-blue-50 border-y-2 border-blue-200">
                                            <td class="px-2 py-2"><input type="date" v-model="editForm.date" class="w-full text-xs"></td>
                                            <td class="px-2 py-2 font-medium text-gray-800">{{ deposit.user?.name }}</td>
                                            <td class="px-2 py-2"><input type="text" v-model="editForm.description" class="w-full text-xs" placeholder="Description"></td>
                                            <td class="px-2 py-2"><input type="number" step="0.01" v-model="editForm.amount" class="w-24 text-xs text-right"></td>
                                            <td class="px-2 py-2 text-center text-xs">{{ deposit.status }}</td>
                                            <td class="px-2 py-2">
                                                <div class="flex justify-center gap-1">
                                                    <button @click="submitEdit" class="bg-blue-600 text-white px-2 py-1 rounded text-xs">Save</button>
                                                    <button @click="editingId = null" class="bg-gray-300 text-gray-700 px-2 py-1 rounded text-xs">Cancel</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-else class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ deposit.date?.slice(0,10) }}</td>
                                            <td class="px-4 py-3 font-medium text-gray-800">{{ deposit.user?.name }}</td>
                                            <td class="px-4 py-3 text-gray-500 text-xs">{{ deposit.description || '—' }}</td>
                                            <td :class="['px-4 py-3 text-right font-semibold', deposit.amount < 0 ? 'text-red-600' : 'text-blue-700']">
                                                ৳ {{ parseFloat(deposit.amount).toFixed(2) }}
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1', statusBadge(deposit.status)]">
                                                    {{ deposit.status }}
                                                </span>
                                            </td>
                                            <td v-if="canManage" class="px-4 py-3">
                                                <div class="flex justify-center gap-2">
                                                    <button @click="startEdit(deposit)" class="rounded-md bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-200 transition">
                                                        ✎ Edit
                                                    </button>
                                                    <button v-if="deposit.status === 'pending'"
                                                        @click="approve(deposit.id)"
                                                        class="rounded-md bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-200 transition">
                                                        ✓ Approve
                                                    </button>
                                                    <button v-if="deposit.status === 'pending'"
                                                        @click="reject(deposit.id)"
                                                        class="rounded-md bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-200 transition">
                                                        ✕ Reject
                                                    </button>
                                                    <button @click="destroy(deposit.id)"
                                                        class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200 transition">
                                                        🗑
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
