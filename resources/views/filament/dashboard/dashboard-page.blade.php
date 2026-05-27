<x-filament-panels::page class="tm-dashboard-page">
    @once
        <style>
            .tm-dashboard-page [data-dashboard-clock] .tm-clock-hand {
                position: absolute;
                left: 50%;
                bottom: 50%;
                transform-origin: center bottom;
                border-radius: 999px;
            }
        </style>
    @endonce

    @php
        $clockLocale = app()->getLocale() === 'ar' ? 'ar-SA-u-ca-gregory' : 'en-US';
    @endphp

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4">
        <section
            data-dashboard-clock
            x-data="{
                now: new Date(),
                timer: null,
                locale: @js($clockLocale),
                init() {
                    this.tick();
                    this.timer = window.setInterval(() => this.tick(), 1000);
                },
                tick() {
                    this.now = new Date();
                },
                get hourRotation() {
                    return ((this.now.getHours() % 12) * 30) + (this.now.getMinutes() * 0.5);
                },
                get minuteRotation() {
                    return (this.now.getMinutes() * 6) + (this.now.getSeconds() * 0.1);
                },
                get secondRotation() {
                    return this.now.getSeconds() * 6;
                },
                get dateLabel() {
                    return new Intl.DateTimeFormat(this.locale, {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric',
                    }).format(this.now);
                },
                get timeLabel() {
                    return new Intl.DateTimeFormat(this.locale, {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: false,
                    }).format(this.now);
                },
            }"
            class="mx-auto w-full max-w-sm rounded-[1.75rem] border border-gray-200 bg-white px-6 py-5 text-center shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <div class="relative mx-auto h-40 w-40 rounded-full border border-gray-200 bg-gray-50 shadow-inner dark:border-white/10 dark:bg-gray-950">
                @for ($tick = 0; $tick < 12; $tick++)
                    <span class="absolute inset-3" style="transform: rotate({{ $tick * 30 }}deg);">
                        <span class="mx-auto block rounded-full bg-gray-300 dark:bg-gray-600 {{ $tick % 3 === 0 ? 'h-4 w-0.5' : 'h-2.5 w-px' }}"></span>
                    </span>
                @endfor

                <span
                    class="tm-clock-hand h-10 w-1.5 bg-gray-900 dark:bg-white"
                    :style="`transform: translateX(-50%) rotate(${hourRotation}deg)`"
                ></span>

                <span
                    class="tm-clock-hand h-14 w-1 bg-gray-500 dark:bg-gray-300"
                    :style="`transform: translateX(-50%) rotate(${minuteRotation}deg)`"
                ></span>

                <span
                    class="tm-clock-hand h-[4.4rem] w-0.5 bg-amber-500"
                    :style="`transform: translateX(-50%) rotate(${secondRotation}deg)`"
                ></span>

                <span class="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-gray-900 dark:bg-white"></span>
            </div>

            <p data-dashboard-date class="mt-5 text-sm font-medium text-gray-500 dark:text-gray-400" x-text="dateLabel"></p>
            <p data-dashboard-time class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white tabular-nums" x-text="timeLabel"></p>
        </section>

        <div class="w-full">
            {{ $this->content }}
        </div>
    </div>
</x-filament-panels::page>
