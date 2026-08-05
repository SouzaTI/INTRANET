<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Documentos - Portal Souza</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'portal-bg': '#f0f2f5',
                        'card-bg': '#ffffff',
                        'texto-principal': '#1f2937',
                        'texto-secundario': '#4b5563',
                        'accent': '#3b82f6',
                    }
                }
            }
        }
    </script>
    <style>
        .transicao { transition: all 0.2s ease-in-out; }
    </style>
</head>
<body class="bg-portal-bg text-texto-principal font-sans p-6 md:p-10">

    <header class="mb-12 text-center border-b border-gray-200 pb-8">
        <h1 class="text-4xl font-extrabold text-texto-principal tracking-tight">Central de Documentos</h1>
        <p class="text-xl text-texto-secundario mt-3 max-w-2xl mx-auto">Escolha uma das áreas abaixo para acessar os registros técnicos e materiais de estudo.</p>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-7xl mx-auto">

        <!-- === Card 1: Software e Gestão === -->
        <a href="software.php" class="bg-card-bg p-8 rounded-3xl shadow-xl border border-gray-100 transicao hover:shadow-2xl hover:-translate-y-1 hover:border-accent/20 flex flex-col">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-5xl p-4 bg-blue-50 rounded-2xl">💻</span>
                <h2 class="text-2xl font-bold text-texto-principal">processos homologação</h2>
            </div>
            <div class="mt-auto inline-flex items-center text-accent font-medium hover:text-blue-600">
                Ver todos os docs <span class="ml-1">→</span>
            </div>
        </a>

        <!-- === Card 2: Hardware e Sistemas Físicos === -->
        <a href="hardware.php" class="bg-card-bg p-8 rounded-3xl shadow-xl border border-gray-100 transicao hover:shadow-2xl hover:-translate-y-1 hover:border-accent/20 flex flex-col">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-5xl p-4 bg-orange-50 rounded-2xl">🛠️</span>
                <h2 class="text-2xl font-bold text-texto-principal">Envio de Processos</h2>
            </div>
            <div class="mt-auto inline-flex items-center text-orange-600 font-medium hover:text-orange-800">
                Ver todos os docs <span class="ml-1">→</span>
            </div>
        </a>

        <!-- === Card 3: Inteligência Artificial === -->
        <a href="ia.php" class="bg-card-bg p-8 rounded-3xl shadow-xl border border-gray-100 transicao hover:shadow-2xl hover:-translate-y-1 hover:border-accent/20 flex flex-col">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-5xl p-4 bg-purple-50 rounded-2xl">🧠</span>
                <h2 class="text-2xl font-bold text-texto-principal">Documentação</h2>
            </div>
            <div class="mt-auto inline-flex items-center text-purple-600 font-medium hover:text-purple-800">
                Ver todos os docs <span class="ml-1">→</span>
            </div>
        </a>

    </div>

</body>
</html>