<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    activeSession: { type: Object, default: null },
    users: { type: Array, default: () => [] },
    meals: { type: Array, default: () => [] },
    pendingMeals: { type: Array, default: () => [] },
    today: String,
    canManage: Boolean,
    userRole: String,
});

// ─── Shared Logic ─────────────────────────────────────────────────────────────
const days = computed(() => {
    if (!props.activeSession) return [];
    const start = new Date(props.activeSession.start_date);
    const end = new Date(props.activeSession.end_date);
    const arr = [];
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        arr.push(new Date(d).toISOString().slice(0, 10));
    }
    return arr;
});

const toDateStr = (val) => val ? String(val).slice(0, 10) : '';

const getDayNameAndNumber = (dateStr) => {
    const d = new Date(dateStr);
    const dayName = d.toLocaleDateString('en-US', { weekday: 'short' });
    const dayNum = d.getDate();
    return `${dayNum} (${dayName})`;
};

const getDayShortName = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'short' });
};

const getMeal = (userId, date) => {
    return props.meals.find(m => m.user_id === userId && toDateStr(m.date) === date) || null;
};

import { usePage } from '@inertiajs/vue3';

const page = usePage();

// ─── Meal Submission (Borders submit for approval, Managers direct save) ──────
const mealForm = useForm({
    user_id: props.canManage ? (props.users[0]?.id || page.props.auth.user.id) : page.props.auth.user.id,
    month_session_id: props.activeSession?.id,
    date: props.today,
    lunch: 0,
    dinner: 0,
    guest_lunch: 0,
    guest_dinner: 0,
});

watch([() => mealForm.user_id, () => mealForm.date], ([newUserId, newDate]) => {
    if (props.canManage && newUserId && newDate) {
        const m = getMeal(newUserId, newDate);
        if (m) {
            mealForm.lunch = parseFloat(m.lunch ?? 0);
            mealForm.dinner = parseFloat(m.dinner ?? 0);
            mealForm.guest_lunch = parseFloat(m.guest_lunch ?? 0);
            mealForm.guest_dinner = parseFloat(m.guest_dinner ?? 0);
        } else {
            mealForm.lunch = 0;
            mealForm.dinner = 0;
            mealForm.guest_lunch = 0;
            mealForm.guest_dinner = 0;
        }
    }
}, { immediate: true });

const submitMeal = () => {
    if (!props.canManage) {
        mealForm.user_id = page.props.auth.user.id;
    }
    mealForm.month_session_id = props.activeSession?.id;
    mealForm.post(route('meals.store'), {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.canManage) {
                mealForm.reset('lunch', 'dinner', 'guest_lunch', 'guest_dinner');
            }
        },
    });
};

// ─── Manager Actions ──────────────────────────────────────────────────────────
const approveMeal = (id) => router.post(route('meals.approve', id), { _method: 'patch' }, { preserveScroll: true });
const rejectMeal = (id) => router.post(route('meals.reject', id), { _method: 'patch' }, { preserveScroll: true });

const cycle = (current) => {
    if (current === 0) return 0.5;
    if (current === 0.5) return 1;
    return 0;
};
const cycleLabel = (meal, field) => {
    if (!meal) return '';
    const val = parseFloat(meal[field] ?? 0);
    const guest = parseFloat(meal['guest_' + field] ?? 0);

    let label = val === 1 ? '1' : (val === 0.5 ? '½' : '0');
    if (guest > 0) label += ` +${guest}G`;
    return label;
};

const cycleMeal = (userId, date, field) => {
    if (!props.canManage) return;

    const existing = getMeal(userId, date);
    const lunch = field === 'lunch' ? cycle(parseFloat(existing?.lunch ?? 0)) : parseFloat(existing?.lunch ?? 0);
    const dinner = field === 'dinner' ? cycle(parseFloat(existing?.dinner ?? 0)) : parseFloat(existing?.dinner ?? 0);

    // Preserve guest meals when cycling
    const guest_lunch = parseFloat(existing?.guest_lunch ?? 0);
    const guest_dinner = parseFloat(existing?.guest_dinner ?? 0);

    router.post(route('meals.store'), {
        user_id: userId,
        month_session_id: props.activeSession.id,
        date, lunch, dinner, guest_lunch, guest_dinner
    }, { preserveScroll: true });
};

const isEditMode = ref(false);
const localMeals = ref({});
const isSavingBulk = ref(false);

const toggleEditMode = () => {
    if (isEditMode.value) {
        isSavingBulk.value = true;
        const bulk = Object.values(localMeals.value);
        router.post(route('meals.bulk-store'), {
            month_session_id: props.activeSession.id,
            meals: bulk
        }, {
            onSuccess: () => { isEditMode.value = false; isSavingBulk.value = false; },
            onError: () => { isSavingBulk.value = false; }
        });
    } else {
        const map = {};
        props.users.forEach(u => {
            days.value.forEach(d => {
                const m = getMeal(u.id, d);
                map[`${u.id}_${d}`] = {
                    user_id: u.id,
                    date: d,
                    lunch: parseFloat(m?.lunch ?? 0),
                    dinner: parseFloat(m?.dinner ?? 0)
                };
            });
        });
        localMeals.value = map;
        isEditMode.value = true;
    }
};

const cancelEdit = () => {
    isEditMode.value = false;
};

const userMealSum = (userId, field) => {
    if (isEditMode.value && localMeals.value) {
        return days.value.reduce((sum, d) => {
            const val = parseFloat(localMeals.value[`${userId}_${d}`]?.[field] ?? 0);
            const m = getMeal(userId, d);
            const guest = parseFloat(m?.['guest_' + field] ?? 0);
            return sum + (isNaN(val) ? 0 : val) + guest;
        }, 0);
    }
    return props.meals
        .filter(m => m.user_id === userId)
        .reduce((sum, m) => {
            return sum + parseFloat(m[field] || 0) + parseFloat(m['guest_' + field] || 0);
        }, 0);
};

const userTotal = (userId) => {
    return userMealSum(userId, 'lunch') + userMealSum(userId, 'dinner');
};

const monthTotal = computed(() => {
    return props.users.reduce((sum, u) => sum + userTotal(u.id), 0);
});

const cellClass = (meal, field) => {
    if (!meal) return 'text-gray-300';
    const val = parseFloat(meal[field] ?? 0);
    const guest = parseFloat(meal['guest_' + field] ?? 0);

    if (guest > 0) return 'bg-purple-100 text-purple-800 font-bold text-[10px]';
    if (val === 1) return 'bg-green-100 text-green-800 font-semibold';
    if (val === 0.5) return 'bg-yellow-100 text-yellow-800 font-semibold';
    return 'text-gray-300';
};
</script>

<template>
    <Head title="Meal Chart" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Meal Chart</h2>
        </template>

        <div class="py-8">
            <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <div v-if="!activeSession" class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                    <p class="text-gray-500">No active month session.</p>
                </div>

                <template v-else>

                    <!-- Pending Approvals (For Managers) -->
                    <div v-if="canManage && pendingMeals.length > 0" class="bg-amber-50 rounded-xl border border-amber-200 p-4">
                        <h3 class="text-amber-800 font-semibold mb-3">Pending Meal Requests</h3>
                        <div class="space-y-2">
                            <div v-for="m in pendingMeals" :key="m.id" class="flex items-center justify-between bg-white p-3 rounded shadow-sm">
                                <div>
                                    <span class="font-medium text-gray-800">{{ m.user.name }}</span>
                                    <span class="text-gray-500 text-sm ml-2">Date: {{ m.date.slice(0,10) }} | L: {{ m.lunch }} | D: {{ m.dinner }} <span v-if="parseFloat(m.guest_lunch) > 0 || parseFloat(m.guest_dinner) > 0" class="text-purple-600 font-semibold">(+ Guest L: {{ m.guest_lunch }}, D: {{ m.guest_dinner }})</span></span>
                                </div>
                                <div class="space-x-2">
                                    <button @click="approveMeal(m.id)" class="px-3 py-1 bg-green-100 text-green-700 rounded text-sm hover:bg-green-200">Approve</button>
                                    <button @click="rejectMeal(m.id)" class="px-3 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200">Reject</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Meal Entry Form (for borders to submit, or for manager to direct-save & add guest meals) -->
                    <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-semibold text-gray-800">
                                {{ canManage ? 'Add / Update Meal & Guest Meals' : 'Submit Your Meal' }}
                            </h3>
                            <span v-if="canManage" class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-1 rounded-full font-bold">
                                👑 Manager Control (Direct Save)
                            </span>
                        </div>
                        <form @submit.prevent="submitMeal" class="flex flex-wrap gap-4 items-end">
                            <div v-if="canManage">
                                <label class="block text-sm text-gray-600 mb-1 font-semibold">Member</label>
                                <select v-model="mealForm.user_id" class="rounded border-gray-300 text-sm font-semibold" required>
                                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Date</label>
                                <input type="date" v-model="mealForm.date" class="rounded border-gray-300 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Lunch</label>
                                <select v-model="mealForm.lunch" class="rounded border-gray-300 text-sm">
                                    <option value="0">0</option>
                                    <option value="0.5">0.5</option>
                                    <option value="1">1</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Dinner</label>
                                <select v-model="mealForm.dinner" class="rounded border-gray-300 text-sm">
                                    <option value="0">0</option>
                                    <option value="0.5">0.5</option>
                                    <option value="1">1</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-purple-700 font-semibold mb-1">Guest Lunch</label>
                                <input type="number" step="0.5" min="0" v-model="mealForm.guest_lunch" class="rounded border-purple-300 text-sm w-20 font-semibold">
                            </div>
                            <div>
                                <label class="block text-sm text-purple-700 font-semibold mb-1">Guest Dinner</label>
                                <input type="number" step="0.5" min="0" v-model="mealForm.guest_dinner" class="rounded border-purple-300 text-sm w-20 font-semibold">
                            </div>
                            <button type="submit" :disabled="mealForm.processing" class="bg-blue-600 text-white px-5 py-2 rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                                {{ canManage ? 'Save Meal' : 'Submit for Approval' }}
                            </button>
                        </form>

                        <!-- Border's own pending requests feedback -->
                        <div v-if="!canManage && pendingMeals.some(m => m.user_id === $page.props.auth.user.id)" class="mt-4 pt-4 border-t border-gray-100">
                            <h4 class="text-xs font-semibold text-amber-700 uppercase tracking-wider mb-2">⏳ Your Pending Requests Awaiting Approval:</h4>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="m in pendingMeals.filter(m => m.user_id === $page.props.auth.user.id)" :key="m.id"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200">
                                    📅 {{ m.date.slice(0, 10) }}: Lunch: {{ m.lunch }}, Dinner: {{ m.dinner }}
                                    <span v-if="parseFloat(m.guest_lunch) > 0 || parseFloat(m.guest_dinner) > 0">(+Guest L: {{ m.guest_lunch }}, D: {{ m.guest_dinner }})</span>
                                    <span class="text-amber-500 font-bold ml-1">● Pending</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Full Grid Header -->
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-gray-800 text-lg">Monthly Meal : : {{ activeSession?.name }}</h3>
                        <div v-if="canManage" class="flex gap-2">
                            <button v-if="!isEditMode" @click="toggleEditMode" class="bg-indigo-100 text-indigo-700 px-4 py-2 rounded text-sm font-semibold hover:bg-indigo-200 transition">
                                ✎ Edit Whole Chart
                            </button>
                            <template v-else>
                                <button @click="cancelEdit" :disabled="isSavingBulk" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-semibold hover:bg-gray-300 transition">
                                    Cancel
                                </button>
                                <button @click="toggleEditMode" :disabled="isSavingBulk" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold hover:bg-indigo-700 transition">
                                    <span v-if="isSavingBulk">Saving...</span>
                                    <span v-else>Save All Changes</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Full Grid -->
                    <div class="overflow-x-auto rounded-xl border-2 border-gray-300 shadow bg-white">
                        <table class="min-w-full text-xs border-collapse">
                            <thead class="bg-gray-100 border-b-2 border-gray-300">
                                <tr>
                                    <th class="sticky left-0 z-20 bg-gray-100 px-3 py-3 text-left font-bold text-gray-700 border-r-2 border-gray-300 min-w-[130px] w-[130px] max-w-[130px] text-xs uppercase tracking-wider">
                                        Member
                                    </th>
                                    <th class="sticky left-[130px] z-20 bg-gray-100 px-1 py-3 text-center font-bold text-gray-700 border-r border-gray-300 min-w-[30px] w-[30px] text-xs uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th v-for="day in days" :key="day"
                                        :class="['px-1 py-2 text-center border-r border-gray-300 font-semibold min-w-[34px]', day === today ? 'bg-blue-100 text-blue-900 ring-2 ring-blue-400 inset-0' : 'text-gray-700 bg-gray-100']">
                                        <div class="font-bold text-xs">{{ parseInt(day.slice(8, 10), 10) }}</div>
                                        <div class="text-[9px] uppercase tracking-tighter text-gray-500 font-medium">{{ getDayShortName(day) }}</div>
                                    </th>
                                    <th class="px-2 py-3 text-center border-l-2 border-gray-300 font-bold text-gray-700 bg-gray-100 min-w-[45px] text-xs uppercase tracking-wider">
                                        L / D
                                    </th>
                                    <th class="sticky right-0 z-20 bg-gray-100 px-3 py-3 text-center font-bold text-gray-800 border-l-2 border-gray-300 shadow-sm min-w-[60px] text-xs uppercase tracking-wider">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="user in users" :key="user.id">
                                    <!-- Lunch Row -->
                                    <tr class="hover:bg-slate-50 transition border-b border-gray-200">
                                        <td rowspan="2" class="sticky left-0 z-10 bg-white px-3 py-2 font-semibold text-gray-800 border-r-2 border-gray-300 whitespace-nowrap shadow-sm align-middle text-sm min-w-[130px] w-[130px] max-w-[130px] truncate" :title="user.name">
                                            {{ user.name }}
                                        </td>
                                        <td class="sticky left-[130px] z-10 bg-blue-50/90 px-1 py-1.5 text-center font-bold text-blue-700 border-r border-gray-300 text-xs min-w-[30px] w-[30px]">
                                            L
                                        </td>
                                        <template v-for="day in days" :key="user.id + '-' + day + '-lunch'">
                                            <template v-if="!isEditMode">
                                                <td @click="cycleMeal(user.id, day, 'lunch')"
                                                    :class="['px-1 py-2 text-center border-r border-gray-300 text-gray-500 min-w-[34px] select-none transition', canManage ? 'cursor-pointer hover:ring-2 hover:ring-blue-400' : '', cellClass(getMeal(user.id, day), 'lunch'), day === today ? 'border-blue-400' : '']">
                                                    {{ cycleLabel(getMeal(user.id, day), 'lunch') }}
                                                </td>
                                            </template>
                                            <template v-else>
                                                <td class="p-0 border-r border-gray-300 min-w-[34px] bg-blue-50/50">
                                                    <input type="number" step="0.5" min="0" max="1" v-model="localMeals[`${user.id}_${day}`].lunch" class="w-full text-center border-none text-xs p-1 h-full bg-transparent focus:bg-white focus:ring-1 focus:ring-blue-400 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                </td>
                                            </template>
                                        </template>
                                        <td class="px-2 py-2 text-center font-bold text-blue-700 border-l-2 border-gray-300 bg-blue-50/40 text-xs">
                                            {{ userMealSum(user.id, 'lunch') }}
                                        </td>
                                        <td rowspan="2" class="sticky right-0 z-10 bg-gray-50 px-3 py-2 text-center font-black text-gray-900 border-l-2 border-gray-300 shadow-sm align-middle text-sm min-w-[60px]">
                                            {{ userTotal(user.id) }}
                                        </td>
                                    </tr>

                                    <!-- Dinner Row -->
                                    <tr class="hover:bg-slate-50 transition border-b-2 border-gray-300">
                                        <td class="sticky left-[130px] z-10 bg-indigo-50/90 px-1 py-1.5 text-center font-bold text-indigo-700 border-r border-gray-300 text-xs min-w-[30px] w-[30px]">
                                            D
                                        </td>
                                        <template v-for="day in days" :key="user.id + '-' + day + '-dinner'">
                                            <template v-if="!isEditMode">
                                                <td @click="cycleMeal(user.id, day, 'dinner')"
                                                    :class="['px-1 py-2 text-center border-r border-gray-300 text-gray-500 min-w-[34px] select-none transition', canManage ? 'cursor-pointer hover:ring-2 hover:ring-indigo-400' : '', cellClass(getMeal(user.id, day), 'dinner'), day === today ? 'border-blue-400' : '']">
                                                    {{ cycleLabel(getMeal(user.id, day), 'dinner') }}
                                                </td>
                                            </template>
                                            <template v-else>
                                                <td class="p-0 border-r border-gray-300 min-w-[34px] bg-indigo-50/50">
                                                    <input type="number" step="0.5" min="0" max="1" v-model="localMeals[`${user.id}_${day}`].dinner" class="w-full text-center border-none text-xs p-1 h-full bg-transparent focus:bg-white focus:ring-1 focus:ring-indigo-400 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                </td>
                                            </template>
                                        </template>
                                        <td class="px-2 py-2 text-center font-bold text-indigo-700 border-l-2 border-gray-300 bg-indigo-50/40 text-xs">
                                            {{ userMealSum(user.id, 'dinner') }}
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Total Monthly Meals Card -->
                    <div class="bg-indigo-50 rounded-xl border border-indigo-200 p-6 flex flex-col items-center justify-center shadow-sm">
                        <h3 class="text-indigo-800 font-semibold mb-1 text-lg">Total Meals (This Month)</h3>
                        <p class="text-4xl font-bold text-indigo-600">{{ monthTotal }}</p>
                    </div>

                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
