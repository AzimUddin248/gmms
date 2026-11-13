<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    activeSession: Object,
    stats: Object,
    memberStats: Array,
    upcomingBazar: Array,
    myBazarRoster: Array,
    myFixedCharges: Object,
    myExpenses: Array,
    today: String,
    pendingMealsCount: Number,
    pendingExpCount: Number,
    pendingDepCount: Number,
    pendingUsers: Number,
    canManage: Boolean,
    isAdmin: Boolean,
});

// Month Modal
const showNewMonthModal = ref(false);
const now = new Date();
const firstDay = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
const monthName = now.toLocaleString('default', { month: 'long', year: 'numeric' });

const monthForm = useForm({
    name: monthName,
    start_date: firstDay,
    end_date: lastDay,
});

const submitMonth = () => {
    monthForm.post(route('month-sessions.store'), {
        onSuccess: () => { showNewMonthModal.value = false; monthForm.reset(); },
    });
};

const closeMonth = () => {
    if (confirm(`Close "${props.activeSession.name}"? No more entries will be allowed.`)) {
        router.post(route('month-sessions.close', props.activeSession.id), { _method: 'patch' });
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        </template>

        <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Pending Alerts -->
            <div v-if="canManage && (pendingMealsCount || pendingExpCount || pendingDepCount || pendingUsers)" class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded shadow-sm">
                <h3 class="text-amber-800 font-semibold mb-2">Pending Actions Required:</h3>
                <ul class="list-disc list-inside text-sm text-amber-700 space-y-1">
                    <li v-if="pendingUsers"><Link :href="route('users.index')" class="underline">{{ pendingUsers }} pending user approvals</Link></li>
                    <li v-if="pendingMealsCount"><Link :href="route('meals.index')" class="underline">{{ pendingMealsCount }} pending meal requests</Link></li>
                    <li v-if="pendingExpCount"><Link :href="route('expenses.index')" class="underline">{{ pendingExpCount }} pending expenses</Link></li>
                    <li v-if="pendingDepCount"><Link :href="route('deposits.index')" class="underline">{{ pendingDepCount }} pending deposits</Link></li>
                </ul>
            </div>

            <!-- No Active Session -->
            <div v-if="!activeSession" class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
                <p class="text-gray-500 mb-4">No active month session.</p>
                <button v-if="canManage" @click="showNewMonthModal = true" class="bg-blue-600 text-white px-5 py-2.5 rounded shadow hover:bg-blue-700">
                    + Start New Month
                </button>
            </div>

            <!-- Bazar Reminder Alert -->
            <div v-if="upcomingBazar && upcomingBazar.length > 0" class="bg-red-50 border-l-4 border-red-500 p-5 rounded shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between animate-pulse">
                <div>
                    <h3 class="text-red-800 font-bold text-lg mb-1">🚨 Bazar Alert!</h3>
                    <p class="text-red-700">You are scheduled for Bazar duty!</p>
                </div>
                <div class="flex flex-col gap-2">
                    <div v-for="bazar in upcomingBazar" :key="bazar.id" class="bg-white rounded border border-red-200 p-3 shadow-sm text-sm">
                        <span class="font-bold text-red-600 block">{{ bazar.date === today ? 'TODAY' : 'TOMORROW' }} ({{ formatDate(bazar.date) }})</span>
                        <span class="text-gray-600 italic mt-1 block">{{ bazar.shopping_list || 'No shopping list provided.' }}</span>
                    </div>
                </div>
            </div>

            <!-- Active Session Stats -->
            <div v-if="activeSession">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">{{ activeSession.name }}</h3>
                        <p class="text-sm text-gray-500">{{ formatDate(activeSession.start_date) }} to {{ formatDate(activeSession.end_date) }}</p>
                    </div>
                    <div v-if="canManage" class="space-x-3">
                        <button @click="showNewMonthModal = true" class="px-4 py-2 bg-white border border-gray-300 rounded text-sm hover:bg-gray-50">New Month</button>
                        <button @click="closeMonth" class="px-4 py-2 bg-red-50 text-red-700 border border-red-200 rounded text-sm hover:bg-red-100">Close Month</button>
                    </div>
                </div>

                <div :class="['grid grid-cols-1 gap-6', canManage ? 'md:grid-cols-4' : 'md:grid-cols-3']">
                    <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex flex-col justify-center items-center text-center col-span-1">
                        <span class="text-gray-500 text-sm font-medium uppercase">Current Meal Rate</span>
                        <span class="text-4xl font-black text-blue-600 mt-2">৳ {{ stats.mealRate.toFixed(2) }}</span>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm grid grid-cols-2 gap-4 col-span-1 md:col-span-1">
                        <div><p class="text-xs text-gray-500 uppercase">Total Meals</p><p class="text-lg font-bold">{{ stats.totalMeals }}</p></div>
                        <div><p class="text-xs text-gray-500 uppercase">Members</p><p class="text-lg font-bold">{{ stats.totalMembers }}</p></div>
                        <div><p class="text-xs text-gray-500 uppercase">Food Cost</p><p class="text-lg font-bold">৳ {{ stats.foodCost }}</p></div>
                        <div><p class="text-xs text-gray-500 uppercase">Total Cost</p><p class="text-lg font-bold">৳ {{ stats.totalExpenses }}</p></div>
                    </div>

                    <!-- Expense Breakdown Pie Chart -->
                    <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center justify-between gap-6 col-span-1">
                        <div class="flex-shrink-0 w-24 h-24 rounded-full relative shadow-inner flex items-center justify-center bg-gray-100"
                            :style="{
                                background: stats.totalExpenses > 0 ? `conic-gradient(
                                    #f43f5e 0% ${stats.expensesBreakdown.rent / stats.totalExpenses * 100}%,
                                    #eab308 ${stats.expensesBreakdown.rent / stats.totalExpenses * 100}% ${(stats.expensesBreakdown.rent + stats.expensesBreakdown.utility) / stats.totalExpenses * 100}%,
                                    #3b82f6 ${(stats.expensesBreakdown.rent + stats.expensesBreakdown.utility) / stats.totalExpenses * 100}% ${(stats.expensesBreakdown.rent + stats.expensesBreakdown.utility + stats.expensesBreakdown.raw_food) / stats.totalExpenses * 100}%,
                                    #8b5cf6 ${(stats.expensesBreakdown.rent + stats.expensesBreakdown.utility + stats.expensesBreakdown.raw_food) / stats.totalExpenses * 100}% 100%
                                )` : '#e5e7eb'
                            }">
                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-sm">
                                <span class="text-xs font-bold text-gray-500">Breakdown</span>
                            </div>
                        </div>
                        <div class="text-xs flex-grow space-y-2 font-medium">
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Rent</span><span>৳ {{ stats.expensesBreakdown.rent }}</span></div>
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span> Utility</span><span>৳ {{ stats.expensesBreakdown.utility }}</span></div>
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Raw Food</span><span>৳ {{ stats.expensesBreakdown.raw_food }}</span></div>
                            <div class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-violet-500"></span> Dry Food</span><span>৳ {{ stats.expensesBreakdown.dry_food }}</span></div>
                        </div>
                    </div>

                    <div v-if="canManage" class="bg-emerald-600 p-6 rounded-xl shadow-sm text-white flex flex-col justify-center col-span-1">
                        <span class="text-emerald-100 text-sm font-medium uppercase">Total Cash Deposits</span>
                        <span class="text-3xl font-black mt-2">৳ {{ stats.totalDeposits }}</span>
                        <Link :href="route('reports.show', activeSession.id)" class="mt-4 text-sm font-medium underline opacity-90 hover:opacity-100">View Full Report &rarr;</Link>
                    </div>
                </div>

                <div v-if="memberStats && memberStats.length > 0" class="mt-8">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">{{ canManage ? 'Member Overview' : 'My Monthly Overview' }}</h3>
                    <div :class="['grid gap-4', canManage ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4' : 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3']">
                        <!-- Member Meal Card -->
                        <div v-for="m in memberStats" :key="m.id" class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                            <div>
                                <h4 class="font-bold text-lg border-b border-gray-100 pb-2 mb-3 text-gray-800">{{ canManage ? m.name : 'Meal & Bazar' }}</h4>
                                <div class="grid grid-cols-2 gap-3 text-sm text-gray-700">
                                    <div class="bg-gray-50 p-2 rounded"><span class="text-[10px] text-gray-500 block uppercase font-bold tracking-wider">Utility</span>৳ {{ m.utility.toFixed(2) }}</div>
                                    <div class="bg-gray-50 p-2 rounded"><span class="text-[10px] text-gray-500 block uppercase font-bold tracking-wider">Meals</span>{{ m.meals }}</div>
                                    <div class="bg-gray-50 p-2 rounded"><span class="text-[10px] text-gray-500 block uppercase font-bold tracking-wider">Raw Food</span>৳ {{ m.rawFood.toFixed(2) }}</div>
                                    <div class="bg-gray-50 p-2 rounded"><span class="text-[10px] text-gray-500 block uppercase font-bold tracking-wider">Dry Food</span>৳ {{ m.dryFood.toFixed(2) }}</div>
                                    <div class="bg-gray-50 p-2 rounded"><span class="text-[10px] text-gray-500 block uppercase font-bold tracking-wider">Deposit</span>৳ {{ (m.deposit || 0).toFixed(2) }}</div>
                                    <div :class="['p-2 rounded border', (m.depositBalance || 0) > 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : ((m.depositBalance || 0) < 0 ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-gray-50 border-gray-200 text-gray-700')]">
                                        <span class="text-[10px] block uppercase font-bold tracking-wider" :class="(m.depositBalance || 0) > 0 ? 'text-emerald-600' : ((m.depositBalance || 0) < 0 ? 'text-rose-600' : 'text-gray-500')">Deposit Balance</span>
                                        <span class="font-bold">৳ {{ (m.depositBalance || 0).toFixed(2) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-blue-50 border border-blue-100 p-2 rounded mt-3 flex justify-between items-center">
                                <span class="text-xs text-blue-800 uppercase font-bold">Meal Cost <span class="text-[10px] font-normal lowercase text-blue-600">({{ m.meals }} × ৳{{ stats.mealRate.toFixed(2) }})</span></span>
                                <span class="font-black text-blue-900 text-base">৳ {{ m.mealCost.toFixed(2) }}</span>
                            </div>
                        </div>

                        <!-- Border: Fixed Charges Card -->
                        <div v-if="!canManage && myFixedCharges" class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                            <div>
                                <h4 class="font-bold text-lg border-b border-gray-100 pb-2 mb-3 text-gray-800">Fixed Charges</h4>

                                <div class="flex justify-between items-center p-1.5 bg-gray-50 rounded border border-gray-100">
                                    <div>
                                        <span class="font-semibold text-gray-800 block text-sm">House Rent</span>
                                    </div>
                                    <span class="font-bold text-gray-800">৳ {{ myFixedCharges.rent.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between items-center p-1.5 bg-gray-50 rounded border border-gray-100">
                                    <div>
                                        <span class="font-semibold text-gray-800 block text-sm">Water / Trash</span>
                                    </div>
                                    <span class="font-bold text-gray-800">৳ {{ myFixedCharges.water.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between items-center p-1.5 bg-gray-50 rounded border border-gray-100">
                                    <div>
                                        <span class="font-semibold text-gray-800 block text-sm">Servant (বুয়া)</span>
                                    </div>
                                    <span class="font-bold text-gray-800">৳ {{ myFixedCharges.servant.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between items-center p-1.5 bg-gray-50 rounded border border-gray-100">
                                    <div>
                                        <span class="font-semibold text-gray-800 block text-sm">Gas / Net / Util</span>
                                    </div>
                                    <span class="font-bold text-gray-800">৳ {{ myFixedCharges.gas_electricity_internet.toFixed(2) }}</span>
                                </div>
                            </div>
                            <div class="bg-purple-50 border border-purple-100 p-2 rounded mt-3 flex justify-between items-center">
                                <span class="text-xs text-purple-800 uppercase font-bold">Total Fixed Share</span>
                                <span class="font-black text-purple-900 text-base">৳ {{ myFixedCharges.total.toFixed(2) }}</span>
                            </div>
                        </div>

                        <!-- Border: My Expense List Card -->
                        <div v-if="!canManage" class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-center border-b border-gray-100 pb-2 mb-3">
                                    <h4 class="font-bold text-lg text-gray-800">My Expense List</h4>
                                    <span class="text-xs text-gray-500 font-medium">{{ myExpenses ? myExpenses.length : 0 }} items</span>
                                </div>
                                <div v-if="myExpenses && myExpenses.length > 0" class="max-h-48 overflow-y-auto space-y-1.5 pr-1 text-xs">
                                    <div v-for="exp in myExpenses" :key="exp.id" class="flex justify-between items-center p-1.5 bg-gray-50 rounded border border-gray-100">
                                        <div>
                                            <span class="font-semibold text-gray-800 block">{{ exp.description || (exp.category === 'raw_food' ? 'Raw Food' : (exp.category === 'dry_food' ? 'Dry Food' : 'Utility')) }}</span>
                                            <span class="text-[10px] text-gray-400">{{ exp.date?.slice(0, 10) }} • <span class="capitalize">{{ exp.category?.replace('_', ' ') }}</span></span>
                                        </div>
                                        <span class="font-bold text-gray-800">৳ {{ parseFloat(exp.amount).toFixed(2) }}</span>
                                    </div>
                                </div>
                                <div v-else class="text-gray-400 text-xs text-center py-6">
                                    No expenses recorded yet.
                                </div>
                            </div>
                            <div class="bg-emerald-50 border border-emerald-100 p-2 rounded mt-3 flex justify-between items-center">
                                <span class="text-xs text-emerald-800 uppercase font-bold">Total Expenses</span>
                                <span class="font-black text-emerald-900 text-base">
                                    ৳ {{ myExpenses ? myExpenses.reduce((sum, e) => sum + parseFloat(e.amount || 0), 0).toFixed(2) : '0.00' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!canManage && myBazarRoster && myBazarRoster.length > 0" class="mt-8">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">My Bazar Roster</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                            <div v-for="bazar in myBazarRoster" :key="bazar.id" class="border-b border-b-red border-dashed py-2" :class="{'border-red-400 bg-red-50': bazar.date === today}">
                                <div>
                                    <span class="font-bold text-gray-800 block mb-1 text-lg">{{ formatDate(bazar.date) }}</span>
                                    <span class="text-sm text-gray-600 block italic mb-2">{{ bazar.shopping_list || 'No shopping list provided.' }}</span>
                                </div>
                                <span v-if="bazar.date === today" class="inline-block mt-2 px-2 py-1 bg-red-600 text-white text-[10px] font-bold rounded uppercase self-start animate-pulse">Today</span>
                                <span v-else-if="bazar.date < today" class="inline-block mt-2 px-2 py-1 bg-gray-200 text-gray-600 text-[10px] font-bold rounded uppercase self-start">Done</span>
                                <span v-else class="inline-block mt-2 px-2 py-1 bg-blue-100 text-blue-800 text-[10px] font-bold rounded uppercase self-start">Upcoming</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Month Modal -->
        <Teleport to="body">
            <div v-if="showNewMonthModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-md bg-white rounded-xl shadow-xl p-6">
                    <h3 class="text-lg font-bold mb-4">Start New Month</h3>
                    <form @submit.prevent="submitMonth" class="space-y-4">
                        <div><label class="block text-sm mb-1">Name</label><input v-model="monthForm.name" class="w-full rounded border-gray-300" required></div>
                        <div><label class="block text-sm mb-1">Start</label><input type="date" v-model="monthForm.start_date" class="w-full rounded border-gray-300" required></div>
                        <div><label class="block text-sm mb-1">End</label><input type="date" v-model="monthForm.end_date" class="w-full rounded border-gray-300" required></div>
                        <div class="flex justify-end gap-2 mt-6">
                            <button type="button" @click="showNewMonthModal = false" class="px-4 py-2 border rounded">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Start</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
