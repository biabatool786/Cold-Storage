<x-layouts.authenticated title="Reports">
    <h2 class="text-2xl font-semibold tracking-tight">Platform reports</h2>
    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Tenants" :value="$tenants" hint="Cold storage companies" />
        <x-kpi label="Users" :value="$users" hint="All accounts" />
        <x-kpi label="Active subscriptions" :value="$activeSubscriptions" hint="Status active" />
        <x-kpi label="Plans" :value="$plans" hint="Platform plans" />
    </div>
</x-layouts.authenticated>
