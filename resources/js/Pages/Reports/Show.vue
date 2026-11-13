<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    session: Object,
    nextMonthName: String,
    memberSummary: Array,
    totals: Object,
    activeSession: Object,
});

const page = usePage();
const canManage = ['admin', 'manager'].includes(page.props.auth.user.role);

import { ref } from 'vue';

const selectedUserId = ref(page.props.auth.user.id);

const selectedSummary = computed(() => props.memberSummary.find(m => m.id === selectedUserId.value));

const carryForward = (userId, userName, balance) => {
    if (!props.activeSession) {
        alert('There is no active month to carry this balance forward to.');
        return;
    }
    const message = balance >= 0
        ? `Carry forward +৳${Math.abs(balance)} (Mess owes ${userName}) to ${props.activeSession.name}?`
        : `Carry forward -৳${Math.abs(balance)} (${userName} owes Mess) to ${props.activeSession.name}?`;

    if (confirm(message)) {
        router.post(route('reports.carry-forward', [props.session.id, userId]), {
            balance: balance
        }, { preserveScroll: true });
    }
};

const generatePayable = (userId) => {
    router.post(route('payables.generate', props.session.id), {
        user_id: userId
    }, { preserveScroll: true });
};

const generateAllPayables = () => {
    if (confirm(`Generate or update payables for all members with due balance for ${props.session.name}?`)) {
        router.post(route('payables.generate', props.session.id), {
            user_id: 'all'
        }, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="session.name + ' Report'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    <Link :href="route('reports.index')" class="text-gray-400 hover:text-gray-600 print:hidden">Reports /</Link>
                    {{ session.name }} Closing Statement
                </h2>
                <select v-if="canManage" v-model="selectedUserId" class="text-xs py-2 rounded border-gray-300 font-bold text-gray-700 print:hidden">
                    <option value="all">All Members (Print)</option>
                    <option v-for="mem in memberSummary" :key="mem.id" :value="mem.id">{{ mem.name }}</option>
                </select>
                <button onclick="window.print()" class="px-4 py-2 bg-gray-800 text-white rounded shadow text-sm hover:bg-gray-700 hidden sm:block print:hidden">🖨 Print</button>
            </div>
        </template>

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 print:py-1 print:space-y-4">
            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 print:hidden">
                <div class="bg-white p-4 rounded-xl border border-gray-200">
                    <p class="text-xs text-gray-500 uppercase">Total Meals</p>
                    <p class="text-2xl font-bold">{{ totals.totalMeals }}</p>
                </div>
                <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                    <p class="text-xs text-blue-600 uppercase">Meal Rate</p>
                    <p class="text-2xl font-bold text-blue-800">৳ {{ totals.mealRate.toFixed(2) }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200">
                    <p class="text-xs text-gray-500 uppercase">Food Cost</p>
                    <p class="text-2xl font-bold text-gray-800">৳ {{ totals.foodCost }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-gray-200">
                    <p class="text-xs text-gray-500 uppercase">Fixed Cost (Rent/Utility)</p>
                    <p class="text-2xl font-bold text-gray-800">৳ {{ totals.fixedCost }}</p>
                </div>
            </div>
            <!-- Personal Payslip Card -->
            <div :class="{'grid grid-cols-1 gap-6': true, 'md:grid-cols-2 print:grid-cols-2 print:gap-4 print:max-w-none print:w-full': selectedUserId === 'all'}" class="w-full max-w-7xl mx-auto">
                <template v-for="summary in (selectedUserId === 'all' ? memberSummary : [selectedSummary])" :key="summary?.id">
                    <div v-if="summary" class="bg-white rounded-xl border-2 border-gray-800 p-6 shadow max-w-2xl mx-auto w-full print:border-gray-300 print:shadow-none relative print:break-inside-avoid">

                <div class="absolute top-4 right-4 flex gap-2 print:hidden items-center">
                    <!-- Positive balance: send as deposit -->
                    <button
                        v-if="canManage && summary.balance > 0 && activeSession"
                        @click="carryForward(summary.id, summary.name, summary.balance)"
                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded shadow-sm transition"
                        title="Transfer positive balance to next month as deposit"
                    >
                        Deposit to Next Month ⭢
                    </button>

                    <!-- Negative balance: generate payable or view link -->
                    <template v-if="canManage && summary.balance < 0">
                        <Link
                            v-if="summary.payable"
                            :href="route('payables.index', { session_id: session.id, user_id: summary.id })"
                            class="px-2.5 py-1 text-xs font-bold rounded border transition"
                            :class="summary.payable.status === 'paid' ? 'bg-green-100 text-green-800 border-green-300' : 'bg-rose-100 text-rose-800 border-rose-300'"
                        >
                            {{ summary.payable.status === 'paid' ? '✅ Paid' : `Due: ৳${summary.payable.due_amount}` }}
                        </Link>
                        <button
                            v-else
                            @click="generatePayable(summary.id)"
                            class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded shadow-sm transition"
                        >
                            ⚡ Generate Payable
                        </button>
                    </template>

                    <button onclick="window.print()" class="px-3 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded border border-gray-300 hover:bg-gray-200">
                        🖨 Save PDF
                    </button>
                </div>

                <div class="text-center border-b border-gray-200 pb-4 mb-4 mt-8 sm:mt-0">
                    <h3 class="text-xl font-bold uppercase tracking-widest text-gray-800">{{ nextMonthName }} - Payslip</h3>
                    <p class="text-gray-600 font-semibold text-base mt-0.5">{{ summary.name }}</p>
                </div>

                <div class="space-y-2 text-sm text-gray-700">
                    <!-- Rent, Water, Gas (Sub Total 1) -->
                    <div class="space-y-1">
                        <div class="flex justify-between items-center text-red-700">
                            <span>বাসা ভাড়া</span>
                            <span class="font-bold">- ৳ {{ summary.rentShare.toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-red-700">
                            <span>পানি/ময়লা</span>
                            <span class="font-bold">- ৳ {{ summary.waterShare.toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-red-700">
                            <span>গ্যাস/বিদ্যুৎ/ইন্টারনেট</span>
                            <span class="font-bold">- ৳ {{ (summary.gasShare || 0).toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between items-center font-bold text-gray-800 border-t border-gray-200 pt-1 mt-1">
                            <span>মোট</span>
                            <span>- ৳ {{ (summary.rentShare + summary.waterShare + (summary.gasShare || 0)).toFixed(2) }}</span>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-gray-300"></div>

                    <!-- Meal Closing of Current Session Month -->
                    <div class="py-1 px-2 text-center bg-gray-100 rounded border border-gray-200 my-2">
                        <span class="font-bold text-xs uppercase tracking-wider text-gray-700">Meal Closing of {{ session.name }}</span>
                    </div>

                    <!-- Bazar & Credits -->
                    <div class="space-y-1">
                        <div class="flex justify-between items-center bg-gray-50 p-1 rounded border border-gray-200">
                            <span class="text-gray-500">মোট শুকনা বাজার বাবদ জমা : ৳ {{ summary.totalDeposits.toFixed(2) }}</span>
                            <span class="font-bold text-gray-600"></span>
                        </div>

                        <div>
                            <div class="flex justify-between items-center text-blue-700">
                                <span class="font-bold">শুকনা ও কাঁচা বাজার</span>
                                <span class="font-bold">+ ৳ {{ summary.ownExpenses.toFixed(2) }}</span>
                            </div>
                            <div v-if="summary.items && summary.items.length > 0" class="mt-2 pl-4 border-l-2 border-gray-200 space-y-1">
                                <div v-for="item in summary.items" :key="item.id" class="flex justify-between text-sm text-gray-500">
                                    <span>{{ item.date?.slice(0,10) }} - {{ item.description || (item.category === 'raw_food' ? 'Raw Food' : 'Dry Food') }}{{ item.category === 'dry_food' ? ' (জমা থেকে)' : '' }}</span>
                                    <span>৳ {{ parseFloat(item.amount).toFixed(2) }}</span>
                                </div>
                            </div>
                            <div v-else class="mt-1 pl-4 text-sm text-gray-400">No expenses recorded.</div>
                        </div>
                    </div>

                    <!-- Meal Cost & Meal Balance -->
                    <div class="space-y-1 mt-4">
                        <div class="flex justify-between items-center text-red-700">
                            <span>মিলের খরচ <span class="text-sm text-gray-400">({{ summary.totalMeals }} meals × ৳{{ totals.mealRate.toFixed(2) }})</span></span>
                            <span class="font-bold">- ৳ {{ summary.mealCost.toFixed(2) }}</span>
                        </div>

                        <div class="flex justify-between items-center font-bold text-gray-800 border-t border-gray-200 pt-1 mt-1">
                            <span>মিল ব্যালেন্স</span>
                            <span :class="[(summary.ownExpenses - summary.mealCost) >= 0 ? 'text-green-600' : 'text-red-600']">
                                {{ (summary.ownExpenses - summary.mealCost) >= 0 ? '+' : '-' }} ৳ {{ Math.abs(summary.ownExpenses - summary.mealCost).toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-gray-300"></div>

                    <!-- Utility Expenses (Own Utility) -->
                    <div class="space-y-1">
                        <div>
                            <div class="flex justify-between items-center text-blue-700">
                                <span class="font-bold">ইউটিলিটি বাবদ খরচ</span>
                                <span class="font-bold">+ ৳ {{ summary.ownUtility.toFixed(2) }}</span>
                            </div>
                            <div v-if="summary.utilityItems && summary.utilityItems.length > 0" class="mt-2 pl-4 border-l-2 border-gray-200 space-y-1">
                                <div v-for="item in summary.utilityItems" :key="item.id" class="flex justify-between text-sm text-gray-500">
                                    <span>{{ item.date?.slice(0,10) }} - {{ item.description || 'Utility' }}</span>
                                    <span>৳ {{ parseFloat(item.amount).toFixed(2) }}</span>
                                </div>
                            </div>
                            <div v-else class="mt-1 pl-4 text-sm text-gray-400">No utility expenses recorded.</div>
                        </div>
                        <div class="flex justify-between items-center font-bold text-gray-800 border-t border-gray-200 pt-1 mt-1">
                            <span>মোট</span>
                            <span>+ ৳ {{ summary.ownUtility.toFixed(2) }}</span>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-gray-300"></div>

                    <!-- Servant and Utility Share (Sub Total 2) -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-red-700">
                            <span>বুয়া বিল</span>
                            <span class="font-bold">- ৳ {{ summary.servantShare.toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-red-700">
                            <span>Utility (বিদ্যুৎ/ফিল্টার) Share</span>
                            <span class="font-bold">- ৳ {{ summary.utilityShare.toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between items-center font-bold text-gray-800 border-t border-gray-200 pt-1 mt-1">
                            <span>মোট</span>
                            <span>- ৳ {{ (summary.servantShare + summary.utilityShare).toFixed(2) }}</span>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-800">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-gray-800 text-lg uppercase tracking-wide">Final Balance</span>
                            <span :class="['font-bold text-xl', summary.balance >= 0 ? 'text-green-600' : 'text-red-600']">
                                {{ summary.balance >= 0 ? '+' : '-' }} ৳ {{ Math.abs(summary.balance).toFixed(2) }}
                            </span>

                        </div>
                        <p class="text-right text-xs mt-1.5 font-bold" :class="['font-bold text-xl', summary.balance >= 0 ? 'text-green-600' : 'text-red-600']">
                            ৳ {{ Math.abs(summary.balance).toFixed(0) }}
                            {{ summary.balance >= 0 ? '(পাবেন)' : '(দিবেন)' }}
                        </p>
                    </div>
                </div>
            </div>
            </template>
            </div>

            <!-- Closing Table -->
            <div v-if="canManage" class="bg-white rounded-xl border border-gray-200 overflow-x-auto shadow-sm print:hidden">
                <div class="px-4 py-3 bg-gray-800 text-white font-bold rounded-t-xl print:hidden flex justify-between items-center">
                    <span>Manager View: All Members</span>
                    <button
                        @click="generateAllPayables"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded-lg shadow font-semibold transition flex items-center gap-1.5"
                    >
                        <span>⚡</span> Generate All Payables
                    </button>
                </div>
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Member Name</th>
                            <th class="px-4 py-3 text-center font-semibold border-l">Meals</th>
                            <th class="px-4 py-3 text-right font-semibold">Meal Cost</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-500 text-xs">Fixed Share</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-500 text-xs">Own Bazar</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-500 text-xs">Deposit</th>
                            <th class="px-4 py-3 text-right font-bold border-l-2 bg-gray-100">Due / Payable</th>
                            <th class="px-4 py-3 text-center font-semibold border-l-2 bg-gray-50 print:hidden">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="mem in memberSummary" :key="mem.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">{{ mem.name }}</td>
                            <td class="px-4 py-3 text-center border-l">{{ mem.totalMeals }}</td>
                            <td class="px-4 py-3 text-right">৳ {{ mem.mealCost.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 text-xs">৳ {{ mem.fixedShare.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 text-xs">৳ {{ mem.ownExpenses.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-right text-blue-600 text-xs">৳ {{ mem.totalDeposits.toFixed(2) }}</td>
                            <td :class="['px-4 py-3 text-right font-bold border-l-2', mem.balance >= 0 ? 'text-green-700 bg-green-50' : 'text-red-700 bg-red-50']">
                                ৳ {{ Math.abs(mem.balance).toFixed(2) }}
                                <span class="text-[10px] ml-1">{{ mem.balance >= 0 ? '(পাবেন)' : '(দিবেন)' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center border-l-2 bg-gray-50 print:hidden whitespace-nowrap">
                                <!-- Positive balance: Transfer to next month in one click -->
                                <div v-if="mem.balance > 0">
                                    <button v-if="activeSession"
                                        @click="carryForward(mem.id, mem.name, mem.balance)"
                                        class="text-xs bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold px-2.5 py-1 rounded hover:bg-emerald-200 transition"
                                        title="Transfer positive balance to next month as deposit">
                                        Send as Deposit ⭢
                                    </button>
                                    <span v-else class="text-[10px] text-amber-600 font-semibold">No active month</span>
                                </div>

                                <!-- Negative balance: Generate Payable or View Payment Status -->
                                <div v-else-if="mem.balance < 0">
                                    <div v-if="mem.payable" class="flex items-center justify-center gap-1.5">
                                        <Link :href="route('payables.index', { session_id: session.id, user_id: mem.id })"
                                            class="text-xs font-bold px-2.5 py-1 rounded border transition"
                                            :class="mem.payable.status === 'paid' ? 'bg-green-100 text-green-800 border-green-300' : 'bg-rose-100 text-rose-800 border-rose-300'">
                                            {{ mem.payable.status === 'paid' ? '✅ Paid' : `Due: ৳${mem.payable.due_amount}` }}
                                        </Link>
                                        <button
                                            @click="generatePayable(mem.id)"
                                            class="text-xs text-gray-400 hover:text-indigo-600 px-1 py-0.5 rounded font-bold"
                                            title="Regenerate/Sync Payable"
                                        >
                                            ↻
                                        </button>
                                    </div>
                                    <button v-else
                                        @click="generatePayable(mem.id)"
                                        class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-2.5 py-1 rounded shadow-sm transition">
                                        ⚡ Generate Payable
                                    </button>
                                </div>

                                <span v-else class="text-[10px] text-gray-400 font-medium">Settled (0.00)</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="text-xs text-gray-400 text-center print:hidden mt-8">
                <p>Formula: Due/Payable = (Own Bazar + Utility Expenses) - (Meal Cost + Fixed Share + Utility Share)</p>
                <p>Positive (পাবেন) = Mess owes member. Negative (দিবেন) = Member owes mess.</p>
                <p class="print:hidden mt-2" v-if="canManage && !session.is_active">
                    💡 Click <b>"Carry Forward ⭢"</b> to transfer a positive balance into the next month as a Deposit.<br>
                    Negative balances are settled in cash.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
