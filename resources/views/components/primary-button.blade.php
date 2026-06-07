<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-lg border border-transparent bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-200 transition hover:bg-teal-700 focus:outline-none focus:ring-4 focus:ring-teal-100']) }}>
    {{ $slot }}
</button>
