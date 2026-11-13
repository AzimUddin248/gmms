<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    activeSession: { type: Object,  default: null },
    users:         { type: Array,   default: () => [] },
    expenses:      { type: Array,   default: () => [] },
    canManage:     { type: Boolean, default: false },
});

const page = usePage();

// ─── Add Expense Form ─────────────────────────────────────────────────────────
const showForm = ref(false);

const categories = [
    { value: 'raw_food',  label: '🥦 Raw Food (কাঁচা বাজার)' },
    { value: 'dry_food',  label: '🛒 Dry Food (শুকনা বাজার)' },
    { value: 'utility',   label: '💡 Utility (বিদ্যুৎ/ফিল্টার)' },
    { value: 'rent',      label: '🏠 Rent (বাসা ভাড়া)' },
];

const categoryLabel = (val) => categories.find(c => c.value === val)?.label ?? val;

const form = useForm({
    month_session_id: props.activeSession?.id ?? '',
    user_id:          page.props.auth?.user?.id ?? '',
    date:             new Date().toISOString().slice(0, 10),
    amount:           '',
    category:         'raw_food',
    description:      '',
});

const submit = () => {
    if (!props.canManage) {
        form.user_id = page.props.auth?.user?.id;
    }
    form.month_session_id = props.activeSession?.id ?? '';
    form.post(route('expenses.store'), {
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
    category: '',
    description: '',
});

const startEdit = (e) => {
    editingId.value = e.id;
    editForm.date = e.date?.slice(0, 10) || '';
    editForm.amount = e.amount;
    editForm.category = e.category;
    editForm.description = e.description || '';
};

const submitEdit = () => {
    editForm.post(route('expenses.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; }
    });
};

// ─── Approve / Reject / Delete ────────────────────────────────────────────────
const approve = (id) => router.post(route('expenses.approve', id), { _method: 'patch' }, { preserveScroll: true });
const reject  = (id) => router.post(route('expenses.reject', id), { _method: 'patch' }, { preserveScroll: true });
const destroy = (id) => {
    if (confirm('Delete this expense?')) {
        router.post(route('expenses.destroy', id), { _method: 'delete' }, { preserveScroll: true });
    }
};

// ─── Filter & Sort Logic ──────────────────────────────────────────────────────
const filterUser = ref('');
const filterCategory = ref('');
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

const filteredExpenses = computed(() => {
    let result = props.expenses.filter(e => {
        if (filterUser.value && e.user_id !== filterUser.value) return false;
        if (filterCategory.value && e.category !== filterCategory.value) return false;
        
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const dateMatch = e.date?.toLowerCase().includes(q);
            const userMatch = e.user?.name?.toLowerCase().includes(q);
            const descMatch = e.description?.toLowerCase().includes(q);
            const catMatch = e.category?.toLowerCase().includes(q);
            const amountMatch = String(e.amount).includes(q);
            
            if (!dateMatch && !userMatch && !descMatch && !catMatch && !amountMatch) return false;
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

// ─── Summary Totals ───────────────────────────────────────────────────────────
const approvedExpenses = computed(() => props.expenses.filter(e => e.status === 'approved'));

const summary = computed(() => {
    const totals = { raw_food: 0, dry_food: 0, utility: 0, rent: 0, food: 0, grand: 0 };
    for (const e of approvedExpenses.value) {
        totals[e.category] = (totals[e.category] || 0) + parseFloat(e.amount);
    }
    totals.food  = totals.raw_food + totals.dry_food;
    totals.grand = totals.food + totals.utility + totals.rent;
    return totals;
});

const statusBadge = (status) => {
    if (status === 'approved') return 'bg-green-100 text-green-700 ring-green-200';
    if (status === 'rejected') return 'bg-red-100 text-red-700 ring-red-200';
    return 'bg-amber-100 text-amber-700 ring-amber-200';
};

const categoryBadge = (cat) => {
    if (cat === 'raw_food') return 'bg-emerald-50 text-emerald-700';
    if (cat === 'dry_food') return 'bg-orange-50 text-orange-700';
    if (cat === 'utility')  return 'bg-yellow-50 text-yellow-700';
    return 'bg-purple-50 text-purple-700';
};
</script>

<template>
    <Head title="Expenses" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bazar & Expenses</h2>
                <button v-if="activeSession"
                    @click="showForm = !showForm"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition">
                    {{ showForm ? '✕ Cancel' : '+ Add Expense' }}
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- No active session -->
                <div v-if="!activeSession" class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                    <p class="text-gray-500">No active month session. Start a month from the Dashboard first.</p>
                </div>

                <template v-else>

                    <!-- ─── Summary Cards ───────────────────────────────────── -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-center">
                            <p class="text-xs font-medium text-emerald-600 uppercase tracking-wide">Raw Food</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-800">৳ {{ summary.raw_food.toFixed(2) }}</p>
                        </div>
                        <div class="rounded-xl bg-orange-50 border border-orange-200 p-4 text-center">
                            <p class="text-xs font-medium text-orange-600 uppercase tracking-wide">Dry Food</p>
                            <p class="mt-1 text-2xl font-bold text-orange-800">৳ {{ summary.dry_food.toFixed(2) }}</p>
                        </div>
                        <div class="rounded-xl bg-yellow-50 border border-yellow-200 p-4 text-center">
                            <p class="text-xs font-medium text-yellow-600 uppercase tracking-wide">Utility</p>
                            <p class="mt-1 text-2xl font-bold text-yellow-800">৳ {{ summary.utility.toFixed(2) }}</p>
                        </div>
                        <div class="rounded-xl bg-purple-50 border border-purple-200 p-4 text-center">
                            <p class="text-xs font-medium text-purple-600 uppercase tracking-wide">Rent</p>
                            <p class="mt-1 text-2xl font-bold text-purple-800">৳ {{ summary.rent.toFixed(2) }}</p>
                        </div>
                    </div>

                    <!-- Food total + Grand total -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-blue-700">Total Food Cost (for Meal Rate)</p>
                                <p class="text-xs text-blue-500 mt-0.5">Raw + Dry food only</p>
                            </div>
                            <p class="text-2xl font-bold text-blue-800">৳ {{ summary.food.toFixed(2) }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-800 border border-gray-700 p-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-300">Grand Total</p>
                                <p class="text-xs text-gray-400 mt-0.5">All approved expenses</p>
                            </div>
                            <p class="text-2xl font-bold text-white">৳ {{ summary.grand.toFixed(2) }}</p>
                        </div>
                    </div>

                    <!-- ─── Add Expense Form ─────────────────────────────────── -->
                    <div v-if="showForm" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                        <h3 class="text-base font-semibold text-gray-800 mb-4">Add New Expense</h3>
                        <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- Who paid -->
                            <div v-if="canManage">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Paid By</label>
                                <select v-model="form.user_id" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <option value="" disabled>Select member</option>
                                    <option v-for="u in users" :key="u.id" :value="u.id">
                                        {{ u.name }} (Dep. Bal: ৳ {{ u.available_deposit ?? 0 }})
                                    </option>
                                </select>
                                <p v-if="form.errors.user_id" class="mt-1 text-xs text-red-600">{{ form.errors.user_id }}</p>
                            </div>

                            <!-- Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                                <input v-model="form.date" type="date" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                                <p v-if="form.errors.date" class="mt-1 text-xs text-red-600">{{ form.errors.date }}</p>
                            </div>

                            <!-- Amount -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amount (৳)</label>
                                <input v-model="form.amount" type="number" step="0.01" min="0" placeholder="0.00" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                                <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
                            </div>

                            <!-- Category -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                <select v-model="form.category" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <option v-for="cat in categories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
                                </select>
                                <p v-if="form.errors.category" class="mt-1 text-xs text-red-600">{{ form.errors.category }}</p>
                            </div>

                            <!-- Description -->
                            <div class="sm:col-span-2 lg:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                                <input v-model="form.description" type="text" placeholder="e.g. Chicken, Rice..."
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                            </div>

                            <!-- Submit -->
                            <div class="flex items-end">
                                <button type="submit" :disabled="form.processing"
                                    class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 transition">
                                    {{ form.processing ? 'Saving...' : 'Add Expense' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ─── Expense List ─────────────────────────────────────── -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <h3 class="font-semibold text-gray-800">All Expenses — {{ activeSession.name }}</h3>
                            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                                <input v-model="searchQuery" type="text" placeholder="Search..." class="text-sm rounded-lg border-gray-300 w-full sm:w-48">
                                <select v-model="filterUser" class="text-sm rounded-lg border-gray-300">
                                    <option value="">All Members</option>
                                    <option v-for="u in users" :value="u.id" :key="u.id">{{ u.name }}</option>
                                </select>
                                <select v-model="filterCategory" class="text-sm rounded-lg border-gray-300">
                                    <option value="">All Categories</option>
                                    <option v-for="cat in categories" :value="cat.value" :key="cat.value">{{ cat.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="filteredExpenses.length === 0" class="px-6 py-10 text-center text-gray-400 text-sm">
                            No expenses match criteria.
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
                                        <th @click="sortBy('category')" class="px-4 py-3 text-left font-semibold text-gray-600 cursor-pointer hover:bg-gray-100 select-none">
                                            Category <span v-if="sortKey === 'category'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
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
                                    <template v-for="expense in filteredExpenses" :key="expense.id">
                                        <tr v-if="editingId === expense.id" class="bg-blue-50 border-y-2 border-blue-200">
                                            <td class="px-2 py-2"><input type="date" v-model="editForm.date" class="w-full text-xs"></td>
                                            <td class="px-2 py-2 font-medium text-gray-800">{{ expense.user?.name }}</td>
                                            <td class="px-2 py-2">
                                                <select v-model="editForm.category" class="w-full text-xs">
                                                    <option v-for="c in categories" :value="c.value" :key="c.value">{{ c.label }}</option>
                                                </select>
                                            </td>
                                            <td class="px-2 py-2"><input type="text" v-model="editForm.description" class="w-full text-xs" placeholder="Description"></td>
                                            <td class="px-2 py-2"><input type="number" step="0.01" min="0" v-model="editForm.amount" class="w-24 text-xs text-right"></td>
                                            <td class="px-2 py-2 text-center text-xs">{{ expense.status }}</td>
                                            <td class="px-2 py-2">
                                                <div class="flex justify-center gap-1">
                                                    <button @click="submitEdit" class="bg-blue-600 text-white px-2 py-1 rounded text-xs">Save</button>
                                                    <button @click="editingId = null" class="bg-gray-300 text-gray-700 px-2 py-1 rounded text-xs">Cancel</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-else class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                                {{ expense.date?.slice(0, 10) }}
                                            </td>
                                            <td class="px-4 py-3 font-medium text-gray-800 whitespace-nowrap">
                                                {{ expense.user?.name }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span :class="['inline-flex rounded-md px-2 py-0.5 text-xs font-medium', categoryBadge(expense.category)]">
                                                    {{ categoryLabel(expense.category) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500">{{ expense.description || '—' }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-gray-800">৳ {{ parseFloat(expense.amount).toFixed(2) }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1', statusBadge(expense.status)]">
                                                    {{ expense.status }}
                                                </span>
                                            </td>
                                            <td v-if="canManage" class="px-4 py-3">
                                                <div class="flex justify-center gap-2">
                                                    <button @click="startEdit(expense)" class="rounded-md bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-200 transition">
                                                        ✎ Edit
                                                    </button>
                                                    <button v-if="expense.status === 'pending'"
                                                        @click="approve(expense.id)"
                                                        class="rounded-md bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-200 transition">
                                                        ✓
                                                    </button>
                                                    <button v-if="expense.status === 'pending'"
                                                        @click="reject(expense.id)"
                                                        class="rounded-md bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-200 transition">
                                                        ✕
                                                    </button>
                                                    <button @click="destroy(expense.id)"
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
