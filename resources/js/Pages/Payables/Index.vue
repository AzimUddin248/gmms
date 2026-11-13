<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    payables: Array,
    sessions: Array,
    users: Array,
    filters: Object,
    totals: Object,
    canManage: Boolean,
    activeSession: Object,
});

// ─── Filter State ─────────────────────────────────────────────────────────────
const filterForm = ref({
    session_id: props.filters.session_id || 'all',
    user_id: props.filters.user_id || 'all',
    status: props.filters.status || 'all',
});

const applyFilters = () => {
    router.get(route('payables.index'), {
        session_id: filterForm.value.session_id,
        user_id: filterForm.value.user_id,
        status: filterForm.value.status,
    }, { preserveState: true, replace: true });
};

// ─── Generate Payables Modal ──────────────────────────────────────────────────
const showGenerateModal = ref(false);
const generateForm = useForm({
    session_id: props.activeSession?.id || (props.sessions[0]?.id ?? ''),
    user_id: 'all',
});

const submitGenerate = () => {
    generateForm.post(route('payables.generate', generateForm.session_id), {
        onSuccess: () => {
            showGenerateModal.value = false;
        },
    });
};

// ─── Collect Payment Modal ────────────────────────────────────────────────────
const showPaymentModal = ref(false);
const selectedPayable = ref(null);

const paymentForm = useForm({
    amount: '',
    date: new Date().toISOString().slice(0, 10),
    payment_method: 'Cash',
    notes: '',
});

const openPaymentModal = (payable) => {
    selectedPayable.value = payable;
    paymentForm.amount = payable.due_amount > 0 ? payable.due_amount : '';
    paymentForm.date = new Date().toISOString().slice(0, 10);
    paymentForm.payment_method = 'Cash';
    paymentForm.notes = '';
    showPaymentModal.value = true;
};

const submitPayment = () => {
    if (!selectedPayable.value) return;
    paymentForm.post(route('payables.payments.store', selectedPayable.value.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            selectedPayable.value = null;
        },
    });
};

// ─── Payment History Modal ────────────────────────────────────────────────────
const showHistoryModal = ref(false);
const historyPayable = ref(null);

const openHistoryModal = (payable) => {
    historyPayable.value = payable;
    showHistoryModal.value = true;
};

const deletePayment = (paymentId) => {
    if (confirm('Delete this payment record? The payable due amount will be recalculated.')) {
        router.delete(route('payables.payments.destroy', paymentId), {
            preserveScroll: true,
            onSuccess: () => {
                showHistoryModal.value = false;
                historyPayable.value = null;
            },
        });
    }
};

const statusBadge = (status) => {
    if (status === 'paid') return 'bg-emerald-100 text-emerald-800 border-emerald-300';
    if (status === 'partial') return 'bg-amber-100 text-amber-800 border-amber-300';
    return 'bg-rose-100 text-rose-800 border-rose-300';
};
</script>

<template>
    <Head title="Payments & Payables" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Payments & Payables
                </h2>
                <div v-if="canManage" class="flex gap-2">
                    <button
                        @click="showGenerateModal = true"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow transition flex items-center gap-1.5"
                    >
                        <span>⚡</span> Generate Payables
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Payable</p>
                        <p class="text-2xl font-black text-gray-800 mt-1">৳ {{ (totals.payable || 0).toFixed(2) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-xl font-bold">
                        📋
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Total Collected (Paid)</p>
                        <p class="text-2xl font-black text-emerald-700 mt-1">৳ {{ (totals.paid || 0).toFixed(2) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center text-xl font-bold">
                        ✅
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-rose-600 uppercase tracking-wider">Total Due (Pending)</p>
                        <p class="text-2xl font-black text-rose-700 mt-1">৳ {{ (totals.due || 0).toFixed(2) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-full flex items-center justify-center text-xl font-bold">
                        ⏳
                    </div>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-wrap gap-4 items-center justify-between">
                <div class="flex flex-wrap gap-3 items-center w-full sm:w-auto">
                    <!-- Session Filter -->
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Month / Session</label>
                        <select
                            v-model="filterForm.session_id"
                            @change="applyFilters"
                            class="text-xs py-2 px-3 rounded-lg border-gray-300 font-semibold text-gray-700 focus:ring-indigo-500"
                        >
                            <option value="all">All Months</option>
                            <option v-for="s in sessions" :key="s.id" :value="s.id">
                                {{ s.name }} {{ s.is_active ? '⭐ (Active)' : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Member Filter (Manager only) -->
                    <div v-if="canManage">
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Border / Member</label>
                        <select
                            v-model="filterForm.user_id"
                            @change="applyFilters"
                            class="text-xs py-2 px-3 rounded-lg border-gray-300 font-semibold text-gray-700 focus:ring-indigo-500"
                        >
                            <option value="all">All Members</option>
                            <option v-for="u in users" :key="u.id" :value="String(u.id)">
                                {{ u.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Payment Status</label>
                        <select
                            v-model="filterForm.status"
                            @change="applyFilters"
                            class="text-xs py-2 px-3 rounded-lg border-gray-300 font-semibold text-gray-700 focus:ring-indigo-500"
                        >
                            <option value="all">All Status</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid (Full)</option>
                        </select>
                    </div>
                </div>

                <div class="text-xs text-gray-400 font-medium">
                    Showing {{ payables.length }} record(s)
                </div>
            </div>

            <!-- Payables Table -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200 text-left">
                        <thead class="bg-gray-50 text-gray-600 font-bold text-xs uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3">Member</th>
                                <th class="px-4 py-3">Closing Month</th>
                                <th class="px-4 py-3 text-right">Payable Amount</th>
                                <th class="px-4 py-3 text-right">Paid</th>
                                <th class="px-4 py-3 text-right">Due</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="p in payables" :key="p.id" class="hover:bg-gray-50 transition">
                                <td class="px-5 py-4 font-semibold text-gray-800">
                                    {{ p.user?.name }}
                                </td>
                                <td class="px-4 py-4 text-gray-600 text-xs">
                                    <span class="font-medium text-gray-800">{{ p.month_session?.name }}</span>
                                    <span v-if="p.notes" class="block text-[10px] text-gray-400 mt-0.5">{{ p.notes }}</span>
                                </td>
                                <td class="px-4 py-4 text-right font-bold text-gray-800">
                                    ৳ {{ parseFloat(p.amount).toFixed(2) }}
                                </td>
                                <td class="px-4 py-4 text-right font-semibold text-emerald-600">
                                    ৳ {{ parseFloat(p.paid_amount).toFixed(2) }}
                                </td>
                                <td class="px-4 py-4 text-right font-black" :class="p.due_amount > 0 ? 'text-rose-600' : 'text-gray-400'">
                                    ৳ {{ parseFloat(p.due_amount).toFixed(2) }}
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span :class="['px-2.5 py-1 rounded-full text-[11px] font-bold border uppercase tracking-wider', statusBadge(p.status)]">
                                        {{ p.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right space-x-2 whitespace-nowrap">
                                    <!-- Collect Payment (Manager only & when due > 0) -->
                                    <button
                                        v-if="canManage && parseFloat(p.due_amount) > 0"
                                        @click="openPaymentModal(p)"
                                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-bold transition shadow-sm"
                                        title="Record a payment"
                                    >
                                        💳 Collect
                                    </button>

                                    <!-- View Payment History -->
                                    <button
                                        @click="openHistoryModal(p)"
                                        class="px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-xs font-semibold border border-gray-300 transition"
                                        title="View payment installments"
                                    >
                                        📜 Payments ({{ p.payments?.length || 0 }})
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="payables.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-gray-400 text-sm">
                                    No payable records found matching your filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- ─── Collect Payment Modal ─────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showPaymentModal && selectedPayable" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 border border-gray-100">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
                        <h3 class="text-lg font-bold text-gray-800">
                            Collect Payment
                        </h3>
                        <button @click="showPaymentModal = false" class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
                    </div>

                    <div class="bg-gray-50 p-3 rounded-xl border border-gray-200 mb-4 text-xs space-y-1">
                        <div class="flex justify-between"><span class="text-gray-500">Member:</span><span class="font-bold text-gray-800">{{ selectedPayable.user?.name }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Month:</span><span class="font-bold text-gray-800">{{ selectedPayable.month_session?.name }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Total Payable:</span><span class="font-bold text-gray-800">৳ {{ parseFloat(selectedPayable.amount).toFixed(2) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Already Paid:</span><span class="font-bold text-emerald-600">৳ {{ parseFloat(selectedPayable.paid_amount).toFixed(2) }}</span></div>
                        <div class="flex justify-between border-t border-gray-200 pt-1 mt-1 font-bold"><span class="text-gray-700">Remaining Due:</span><span class="text-rose-600">৳ {{ parseFloat(selectedPayable.due_amount).toFixed(2) }}</span></div>
                    </div>

                    <form @submit.prevent="submitPayment" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1 uppercase">Payment Amount (৳)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                v-model="paymentForm.amount"
                                class="w-full rounded-lg border-gray-300 text-sm font-bold text-gray-800"
                                placeholder="e.g. 500"
                                required
                            >
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase">Date</label>
                                <input
                                    type="date"
                                    v-model="paymentForm.date"
                                    class="w-full rounded-lg border-gray-300 text-sm"
                                    required
                                >
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase">Method</label>
                                <select v-model="paymentForm.payment_method" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="Cash">Cash (নগদ)</option>
                                    <option value="bKash">bKash</option>
                                    <option value="Nagad">Nagad</option>
                                    <option value="Bank">Bank Transfer</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1 uppercase">Notes / Transaction ID (Optional)</label>
                            <input
                                type="text"
                                v-model="paymentForm.notes"
                                class="w-full rounded-lg border-gray-300 text-sm"
                                placeholder="e.g. 1st installment or TrxID"
                            >
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                            <button
                                type="button"
                                @click="showPaymentModal = false"
                                class="px-4 py-2 border rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="paymentForm.processing"
                                class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700 shadow disabled:opacity-50"
                            >
                                {{ paymentForm.processing ? 'Saving...' : 'Save Payment' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ─── Payment History Modal ─────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showHistoryModal && historyPayable" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl p-6 border border-gray-100">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Payment Installments</h3>
                            <p class="text-xs text-gray-500">{{ historyPayable.user?.name }} • {{ historyPayable.month_session?.name }}</p>
                        </div>
                        <button @click="showHistoryModal = false" class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
                    </div>

                    <div class="mb-4 flex justify-between bg-gray-50 p-3 rounded-xl border border-gray-200 text-xs font-semibold">
                        <span>Total Payable: ৳{{ parseFloat(historyPayable.amount).toFixed(2) }}</span>
                        <span class="text-emerald-700">Paid: ৳{{ parseFloat(historyPayable.paid_amount).toFixed(2) }}</span>
                        <span class="text-rose-700">Due: ৳{{ parseFloat(historyPayable.due_amount).toFixed(2) }}</span>
                    </div>

                    <div v-if="historyPayable.payments && historyPayable.payments.length > 0" class="max-h-64 overflow-y-auto divide-y divide-gray-100 border rounded-xl">
                        <div v-for="pay in historyPayable.payments" :key="pay.id" class="p-3 flex justify-between items-center hover:bg-gray-50 text-xs">
                            <div>
                                <span class="font-bold text-gray-800 text-sm block">৳ {{ parseFloat(pay.amount).toFixed(2) }}</span>
                                <span class="text-gray-500">{{ pay.date?.slice(0, 10) }} • <span class="font-medium text-gray-700">{{ pay.payment_method }}</span></span>
                                <span v-if="pay.notes" class="block text-gray-400 italic text-[11px] mt-0.5">{{ pay.notes }}</span>
                                <span v-if="pay.recorder" class="block text-[10px] text-gray-400">By: {{ pay.recorder.name }}</span>
                            </div>
                            <div v-if="canManage">
                                <button
                                    @click="deletePayment(pay.id)"
                                    class="text-xs text-red-600 hover:text-red-800 font-bold px-2 py-1 rounded hover:bg-red-50 transition"
                                    title="Delete this payment entry"
                                >
                                    Delete ✕
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-gray-400 text-sm">
                        No payments recorded yet for this payable.
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button
                            type="button"
                            @click="showHistoryModal = false"
                            class="px-4 py-2 border rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Generate Payables Modal ───────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showGenerateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 border border-gray-100">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
                        <h3 class="text-lg font-bold text-gray-800">
                            Generate Payables
                        </h3>
                        <button @click="showGenerateModal = false" class="text-gray-400 hover:text-gray-600 text-lg font-bold">✕</button>
                    </div>

                    <p class="text-xs text-gray-500 mb-4">
                        This calculates the closing balances for the selected month and automatically generates or updates payable records for members with due money.
                    </p>

                    <form @submit.prevent="submitGenerate" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1 uppercase">Month Session</label>
                            <select v-model="generateForm.session_id" class="w-full rounded-lg border-gray-300 text-sm font-semibold" required>
                                <option v-for="s in sessions" :key="s.id" :value="s.id">
                                    {{ s.name }} {{ s.is_active ? '⭐ (Active)' : '' }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1 uppercase">Target Member</label>
                            <select v-model="generateForm.user_id" class="w-full rounded-lg border-gray-300 text-sm font-semibold">
                                <option value="all">All Members with Due</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">
                                    {{ u.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                            <button
                                type="button"
                                @click="showGenerateModal = false"
                                class="px-4 py-2 border rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="generateForm.processing"
                                class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 shadow disabled:opacity-50"
                            >
                                {{ generateForm.processing ? 'Generating...' : 'Generate Now' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </AuthenticatedLayout>
</template>
