<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    activeSession: Object,
    users: Array,
    charges: Array,
});

// Prepare the bulk form
const getCharge = (userId) => {
    return props.charges.find(c => c.user_id === userId) || { rent: 0, water: 0, servant: 0, gas_electricity_internet: 0 };
};

const formData = props.users.map(u => {
    const charge = getCharge(u.id);
    return {
        user_id: u.id,
        name: u.name,
        rent: charge.rent,
        water: charge.water,
        servant: charge.servant,
        gas_electricity_internet: charge.gas_electricity_internet || 0,
    };
});

const form = useForm({
    month_session_id: props.activeSession?.id,
    charges: formData
});

const submit = () => {
    form.post(route('fixed-charges.bulk-store'), {
        preserveScroll: true,
    });
};

const totalCol = (field) => {
    return form.charges.reduce((sum, c) => sum + (parseFloat(c[field]) || 0), 0);
};
</script>

<template>
    <Head title="Fixed Charges" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Fixed Charges (Rent, Water, Servant, Gas/Electricity/Internet)</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <div v-if="!activeSession" class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                    <p class="text-gray-500">No active month session.</p>
                </div>
                
                <div v-else class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Set Monthly Charges</h3>
                        <p class="text-sm text-gray-500">These amounts will be deducted from each member's deposit for the month of {{ activeSession.name }}.</p>
                    </div>

                    <form @submit.prevent="submit">
                        <div class="overflow-x-auto border rounded mb-4">
                            <table class="min-w-full text-sm text-left">
                                <thead class="bg-gray-100 text-gray-700 uppercase font-bold text-xs">
                                    <tr>
                                        <th class="px-4 py-3 border-r">Member</th>
                                        <th class="px-4 py-3 border-r">Rent (৳)</th>
                                        <th class="px-4 py-3 border-r">Water/Utility (৳)</th>
                                        <th class="px-4 py-3 border-r">Servant (৳)</th>
                                        <th class="px-4 py-3">Gas/Electricity/Internet (৳)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(charge, i) in form.charges" :key="charge.user_id" class="border-b">
                                        <td class="px-4 py-2 border-r font-semibold">{{ charge.name }}</td>
                                        <td class="px-4 py-2 border-r p-0">
                                            <input type="number" step="0.01" min="0" v-model="charge.rent" class="w-full border-0 bg-transparent focus:ring-0 text-right">
                                        </td>
                                        <td class="px-4 py-2 border-r p-0">
                                            <input type="number" step="0.01" min="0" v-model="charge.water" class="w-full border-0 bg-transparent focus:ring-0 text-right">
                                        </td>
                                        <td class="px-4 py-2 border-r p-0">
                                            <input type="number" step="0.01" min="0" v-model="charge.servant" class="w-full border-0 bg-transparent focus:ring-0 text-right">
                                        </td>
                                        <td class="px-4 py-2 p-0">
                                            <input type="number" step="0.01" min="0" v-model="charge.gas_electricity_internet" class="w-full border-0 bg-transparent focus:ring-0 text-right">
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-indigo-50 font-bold text-indigo-900 border-t-2">
                                    <tr>
                                        <td class="px-4 py-3 border-r text-right">Total Distributed:</td>
                                        <td class="px-4 py-3 border-r text-right">৳ {{ totalCol('rent').toFixed(2) }}</td>
                                        <td class="px-4 py-3 border-r text-right">৳ {{ totalCol('water').toFixed(2) }}</td>
                                        <td class="px-4 py-3 border-r text-right">৳ {{ totalCol('servant').toFixed(2) }}</td>
                                        <td class="px-4 py-3 text-right">৳ {{ totalCol('gas_electricity_internet').toFixed(2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white px-6 py-2.5 rounded shadow hover:bg-indigo-700 font-bold disabled:opacity-50">
                                Save All Charges
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
