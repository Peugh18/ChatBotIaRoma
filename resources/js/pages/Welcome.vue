<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { MessageSquare, Sparkles, ShoppingBag, ArrowRight, CheckCircle2 } from 'lucide-vue-next';
</script>

<template>
    <Head title="Bienvenido a Roma CRM" />

    <div class="flex min-h-screen flex-col items-center justify-between bg-background p-6 text-foreground dark:bg-background lg:justify-center lg:p-8">
        <!-- Header / Navigation -->
        <header class="mb-6 w-full max-w-5xl text-sm">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5 font-medium">
                    <AppLogoIcon class="size-9 text-primary" />
                    <span class="text-xl font-bold tracking-tight">Roma <span class="text-primary">CRM</span></span>
                </div>
                <nav class="flex items-center gap-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="inline-block rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/95"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="inline-block rounded-lg px-4 py-2 text-sm font-semibold text-muted-foreground transition-all hover:text-foreground"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            :href="route('register')"
                            class="inline-block rounded-lg border border-border px-4 py-2 text-sm font-semibold text-foreground transition-all hover:bg-muted"
                        >
                            Registrarse
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Main Panel -->
        <main class="my-auto flex w-full max-w-5xl flex-col-reverse overflow-hidden rounded-2xl border border-border bg-card shadow-xl lg:flex-row">
            <!-- Left Info Panel -->
            <div class="flex-1 p-8 lg:p-16 flex flex-col justify-between">
                <div class="space-y-6">
                    <div>
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary border border-primary/20">
                            <Sparkles class="size-3.5" />
                            <span>Ventas automatizadas por WhatsApp</span>
                        </div>
                        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-foreground sm:text-4xl">
                            Roma <span class="text-primary">CRM</span>
                        </h1>
                        <p class="mt-3 text-base text-muted-foreground leading-relaxed">
                            Gestión inteligente de clientes, pipeline de pedidos y bot conversacional con IA para tu boutique de vestidos.
                        </p>
                    </div>

                    <!-- Features list -->
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary border border-primary/20">
                                <MessageSquare class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-foreground">Bot de WhatsApp e IA</h3>
                                <p class="text-xs text-muted-foreground mt-0.5 leading-relaxed">
                                    Respuestas automáticas inteligentes con fallback a Groq LLM y búsqueda de vestidos.
                                </p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary border border-primary/20">
                                <Sparkles class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-foreground">Reconocimiento Visual Voyage</h3>
                                <p class="text-xs text-muted-foreground mt-0.5 leading-relaxed">
                                    Los clientes envían una foto y el bot reconoce el vestido al instante usando embeddings vectoriales.
                                </p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary border border-primary/20">
                                <ShoppingBag class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-foreground">Pipeline Kanban de Pedidos</h3>
                                <p class="text-xs text-muted-foreground mt-0.5 leading-relaxed">
                                    Organiza tus pedidos desde la validación del Yape hasta la entrega final de manera visual.
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Call to action -->
                <div class="mt-8 pt-8 border-t border-border">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-md transition-all hover:bg-primary/95"
                    >
                        <span>Entrar al panel de control</span>
                        <ArrowRight class="size-4" />
                    </Link>
                    <div v-else class="flex flex-col sm:flex-row gap-3">
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-md transition-all hover:bg-primary/95"
                        >
                            <span>Iniciar Sesión</span>
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            :href="route('register')"
                            class="inline-flex items-center justify-center rounded-lg border border-border px-6 py-3 text-sm font-semibold text-foreground transition-all hover:bg-muted"
                        >
                            Registrar nueva cuenta
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Right Interactive Graphic Panel -->
            <div class="relative w-full lg:w-[440px] shrink-0 bg-muted/20 dark:bg-muted/5 p-8 flex items-center justify-center border-b lg:border-b-0 lg:border-l border-border overflow-hidden">
                <!-- Background decoration patterns -->
                <div class="absolute inset-0 bg-grid-pattern opacity-10"></div>
                <div class="absolute -left-10 -top-10 size-40 rounded-full bg-primary/5 blur-3xl"></div>
                <div class="absolute -right-10 -bottom-10 size-40 rounded-full bg-emerald-500/5 blur-3xl"></div>

                <!-- Graphic Simulator -->
                <div class="relative w-full max-w-[320px] space-y-4 z-10">
                    <!-- WhatsApp Chat Mockup bubble 1 -->
                    <div class="flex flex-col items-start space-y-1">
                        <span class="text-[10px] text-muted-foreground ml-3 font-semibold">Cliente (WhatsApp)</span>
                        <div class="rounded-2xl rounded-bl-none bg-card border border-border p-3 shadow-sm max-w-[85%] text-xs text-foreground">
                            <p class="mb-2">¿Tienen este vestido disponible?</p>
                            <!-- Mini dress mockup image -->
                            <div class="relative aspect-[4/3] rounded-lg bg-primary/5 border border-dashed border-primary/30 flex items-center justify-center overflow-hidden">
                                <span class="text-3xl">👗</span>
                                <div class="absolute bottom-1 right-1 bg-black/60 text-[9px] text-white px-1.5 py-0.5 rounded">
                                    foto.jpg
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Connection indicator -->
                    <div class="flex items-center justify-center gap-2 py-1">
                        <div class="h-px flex-1 bg-gradient-to-r from-transparent to-primary"></div>
                        <div class="flex items-center gap-1 bg-primary/10 px-2.5 py-0.5 rounded-full text-[10px] font-semibold text-primary border border-primary/20">
                            <Sparkles class="size-3 animate-pulse text-primary" />
                            <span>Voyage AI Match</span>
                        </div>
                        <div class="h-px flex-1 bg-gradient-to-l from-transparent to-primary"></div>
                    </div>

                    <!-- Bot Response Mockup bubble 2 -->
                    <div class="flex flex-col items-end space-y-1">
                        <span class="text-[10px] text-muted-foreground mr-3 font-semibold">Bot Roma</span>
                        <div class="rounded-2xl rounded-br-none bg-primary/10 border border-primary/20 p-3 shadow-sm max-w-[90%] text-xs text-foreground space-y-2">
                            <p>¡Sí, hermosa! Encontré este vestido en nuestro catálogo:</p>
                            <!-- Product preview card -->
                            <div class="rounded-lg bg-card border border-border p-2 flex items-center gap-3">
                                <div class="size-10 rounded bg-primary/5 flex items-center justify-center text-lg">
                                    👗
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-bold text-[11px] truncate">Vestido Gala Bella</h4>
                                    <p class="text-[10px] text-muted-foreground font-semibold">S/ 149.00</p>
                                    <div class="mt-1 flex items-center gap-1 text-[9px] text-emerald-600 font-bold dark:text-emerald-400">
                                        <CheckCircle2 class="size-3" />
                                        <span>Rojo - M (En stock)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="mt-6 w-full max-w-5xl text-center text-xs text-muted-foreground">
            <p>&copy; 2026 Roma Vestidos. Todos los derechos reservados.</p>
        </footer>
    </div>
</template>

<style scoped>
.bg-grid-pattern {
    background-size: 20px 20px;
    background-image: 
        linear-gradient(to right, hsl(var(--border) / 0.3) 1px, transparent 1px),
        linear-gradient(to bottom, hsl(var(--border) / 0.3) 1px, transparent 1px);
}
</style>
