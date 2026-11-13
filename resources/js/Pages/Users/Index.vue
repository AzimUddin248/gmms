<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: Array,
    isAdmin: Boolean,
});

// Admin Add User Modal
const showModal = ref(false);
const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'border',
});

const submit = () => {
    form.post(route('users.store'), {
        onSuccess: () => { showModal.value = false; form.reset(); },
    });
};

const approve = (id) => router.post(route('users.approve', id), { _method: 'patch' }, { preserveScroll: true });
const destroy = (id) => {
    if (confirm('Delete this user?')) {
        router.post(route('users.destroy', id), { _method: 'delete' }, { preserveScroll: true });
    }
};
const updateRole = (id, e) => {
    router.post(route('users.update-role', id), { _method: 'patch', role: e.target.value }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Members & Roles</h2>
                <button v-if="isAdmin" @click="showModal = true" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-indigo-700">+ Add Member</button>
            </div>
        </template>

        <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Name</th>
                            <th class="px-4 py-3 text-left font-semibold">Email</th>
                            <th class="px-4 py-3 text-left font-semibold">Role</th>
                            <th class="px-4 py-3 text-center font-semibold">Status</th>
                            <th v-if="isAdmin" class="px-4 py-3 text-center font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                <select v-if="isAdmin && $page.props.auth.user.id !== user.id" :value="user.role" @change="updateRole(user.id, $event)" class="rounded border-gray-300 text-xs py-1 px-2">
                                    <option value="admin">Admin</option>
                                    <option value="manager">Manager</option>
                                    <option value="border">Border</option>
                                </select>
                                <span v-else class="capitalize">{{ user.role }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="user.is_approved" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Approved</span>
                                <span v-else class="text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded-full">Pending</span>
                            </td>
                            <td v-if="isAdmin" class="px-4 py-3 text-center">
                                <div class="flex justify-center gap-2">
                                    <button v-if="!user.is_approved" @click="approve(user.id)" class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs hover:bg-green-200">Approve</button>
                                    <button v-if="$page.props.auth.user.id !== user.id" @click="destroy(user.id)" class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs hover:bg-red-200">Delete</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-md bg-white rounded-xl shadow-xl p-6">
                    <h3 class="text-lg font-bold mb-4">Add New Member</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div><label class="block text-sm mb-1">Name</label><input v-model="form.name" type="text" class="w-full rounded border-gray-300" required></div>
                        <div><label class="block text-sm mb-1">Email</label><input v-model="form.email" type="email" class="w-full rounded border-gray-300" required></div>
                        <div><label class="block text-sm mb-1">Password</label><input v-model="form.password" type="password" class="w-full rounded border-gray-300" required minlength="8"></div>
                        <div>
                            <label class="block text-sm mb-1">Role</label>
                            <select v-model="form.role" class="w-full rounded border-gray-300" required>
                                <option value="border">Border</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 mt-6">
                            <button type="button" @click="showModal = false" class="px-4 py-2 border rounded">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Add User</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
