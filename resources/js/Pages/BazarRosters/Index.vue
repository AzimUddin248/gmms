<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    activeSession: Object,
    rosters: Array,
    users: Array,
    canManage: Boolean,
});

const todayDate = new Date().toISOString().slice(0, 10);
const form = useForm({
    month_session_id: props.activeSession?.id,
    date: todayDate,
    user_id: '',
});

const submit = () => {
    form.post(route('bazar-rosters.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('user_id'),
    });
};

const destroy = (id) => {
    if (confirm('Delete this roster assignment?')) {
        router.post(route('bazar-rosters.destroy', id), { _method: 'delete' }, { preserveScroll: true });
    }
};

const getDayName = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
};

const editingRosterId = ref(null);
const listForm = useForm({
    month_session_id: props.activeSession?.id,
    date: '',
    user_id: '',
    shopping_list: ''
});

const editList = (roster) => {
    editingRosterId.value = roster.id;
    listForm.date = roster.date;
    listForm.user_id = roster.user_id;
    listForm.shopping_list = roster.shopping_list || '';
};

const saveList = () => {
    listForm.post(route('bazar-rosters.store'), {
        preserveScroll: true,
        onSuccess: () => {
            editingRosterId.value = null;
        }
    });
};

const cancelEdit = () => {
    editingRosterId.value = null;
};

const sortKey = ref('date');
const sortOrder = ref('asc');

const sortBy = (key) => {
    if (sortKey.value === key) {
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortOrder.value = 'asc';
    }
};

const filterUser = ref('');
const searchQuery = ref('');

const filteredRosters = computed(() => {
    let result = props.rosters.filter(r => {
        if (filterUser.value && r.user_id !== filterUser.value) return false;
        
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const dateMatch = r.date?.toLowerCase().includes(q);
            const userMatch = r.user?.name?.toLowerCase().includes(q);
            const listMatch = r.shopping_list?.toLowerCase().includes(q);
            
            if (!dateMatch && !userMatch && !listMatch) return false;
        }

        return true;
    });

    result.sort((a, b) => {
        let valA = a[sortKey.value];
        let valB = b[sortKey.value];
        
        if (sortKey.value === 'user_name') {
            valA = a.user?.name || '';
            valB = b.user?.name || '';
        }

        if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1;
        if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1;
        return 0;
    });

    return result;
});
</script>

<template>
    <Head title="Bazar Roster" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bazar Roster</h2>
        </template>

        <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div v-if="!activeSession" class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                <p class="text-gray-500">No active month session.</p>
            </div>

            <template v-else>
                <!-- Manager Form -->
                <div v-if="canManage" class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm">
                    <h3 class="font-semibold mb-4 text-gray-800">Assign Member for Bazar</h3>
                    <form @submit.prevent="submit" class="flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Date</label>
                            <input type="date" v-model="form.date" :min="activeSession.start_date" :max="activeSession.end_date" class="rounded border-gray-300 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Member</label>
                            <select v-model="form.user_id" class="rounded border-gray-300 text-sm" required>
                                <option value="" disabled>Select member</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <button type="submit" :disabled="form.processing" class="bg-emerald-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-emerald-700 disabled:opacity-50">
                            Assign
                        </button>
                    </form>
                </div>

                <!-- Roster List -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-gray-50">
                        <h3 class="font-semibold text-gray-800">All Rosters</h3>
                        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                            <input v-model="searchQuery" type="text" placeholder="Search..." class="text-sm rounded-lg border-gray-300 w-full sm:w-48">
                            <select v-model="filterUser" class="text-sm rounded-lg border-gray-300">
                                <option value="">All Members</option>
                                <option v-for="u in users" :value="u.id" :key="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th @click="sortBy('date')" class="px-4 py-3 text-left font-semibold text-gray-600 w-24 cursor-pointer hover:bg-gray-100 select-none">
                                    Date <span v-if="sortKey === 'date'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 w-24">Day</th>
                                <th @click="sortBy('user_name')" class="px-4 py-3 text-left font-semibold text-gray-600 w-48 cursor-pointer hover:bg-gray-100 select-none">
                                    Assigned To <span v-if="sortKey === 'user_name'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Shopping List</th>
                                <th v-if="canManage" class="px-4 py-3 text-center font-semibold text-gray-600 w-32">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="filteredRosters.length === 0">
                                <td :colspan="canManage ? 5 : 4" class="px-4 py-6 text-center text-gray-400">No roster assignments match criteria.</td>
                            </tr>
                            <tr v-for="roster in filteredRosters" :key="roster.id" :class="[roster.date === todayDate ? 'bg-emerald-50' : 'hover:bg-gray-50']">
                                <td class="px-4 py-3 font-medium align-top" :class="[roster.date === todayDate ? 'text-emerald-700' : 'text-gray-800']">{{ roster.date }}</td>
                                <td class="px-4 py-3 text-gray-500 align-top">{{ getDayName(roster.date) }}</td>
                                <td class="px-4 py-3 text-gray-700 align-top">
                                    <span v-if="roster.date === todayDate" class="text-xs bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded mr-2 font-bold block mb-1 w-max">TODAY</span>
                                    <span class="font-medium">{{ roster.user.name }}</span>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div v-if="editingRosterId === roster.id">
                                        <textarea v-model="listForm.shopping_list" rows="3" class="w-full text-sm rounded border-gray-300 mb-2 focus:ring-emerald-500" placeholder="e.g. 2kg Fish, 1kg Onion..."></textarea>
                                        <div class="flex gap-2">
                                            <button @click="saveList" :disabled="listForm.processing" class="bg-emerald-600 text-white text-xs px-3 py-1 rounded hover:bg-emerald-700">Save</button>
                                            <button @click="cancelEdit" class="bg-gray-200 text-gray-700 text-xs px-3 py-1 rounded hover:bg-gray-300">Cancel</button>
                                        </div>
                                    </div>
                                    <div v-else>
                                        <p v-if="roster.shopping_list" class="whitespace-pre-line text-gray-600">{{ roster.shopping_list }}</p>
                                        <p v-else class="text-gray-400 italic text-xs">No list yet</p>
                                        <button v-if="canManage" @click="editList(roster)" class="text-emerald-600 hover:text-emerald-800 text-xs mt-1 font-medium underline">
                                            {{ roster.shopping_list ? 'Edit List' : '+ Add List' }}
                                        </button>
                                    </div>
                                </td>
                                <td v-if="canManage" class="px-4 py-3 text-center align-top">
                                    <button @click="destroy(roster.id)" class="text-red-500 hover:text-red-700 text-xs px-2 py-1 rounded bg-red-50 hover:bg-red-100">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

        </div>
    </AuthenticatedLayout>
</template>
