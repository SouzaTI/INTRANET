<?php
/**
 * MÓDULO: Sistemas de Navegação / NOC
 *
 * Este arquivo é incluído pelo index_teste.php.
 * Ele reutiliza a sessão, $pdo_intra e $user_id_logado já carregados no index.
 *
 * Contém:
 * - RBAC e leitura de sistemas_lista;
 * - categorias temporárias do NOC;
 * - HTML do modal;
 * - CSS específico;
 * - JavaScript específico da navegação.
 */
?>

<div id="modalSistemas" class="fixed inset-0 z-[1000] hidden items-center justify-center p-4 backdrop-blur-xl bg-navy-900/40 transition-all duration-500">
 <div id="modalSistemasPainel" class="modal-sistemas-painel relative w-[98vw] max-w-[1750px] rounded-[2rem] p-7 animate-in zoom-in-95 duration-300 overflow-hidden">
        
        <button id="btnVoltarModal" onclick="exibirPrincipalSistemas()" class="hidden absolute top-6 left-6 z-30 flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-black transition-all">
            ⬅️ VOLTAR
        </button>

        <div class="absolute -top-24 -right-24 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl"></div>
       
        <button onclick="fecharModalSistemas()" class="absolute top-5 right-6 text-white/30 hover:text-white transition-colors text-3xl font-light z-30">&times;</button>

         <div class="modal-sistemas-header mb-8 flex flex-col md:flex-row justify-between items-start md:items-center border-b border-cyan-400/10 pb-4 mt-4 md:mt-0">
            <div>
                <h2 id="tituloModalSistemas" class="text-white text-xl font-black tracking-tighter uppercase italic">Sistemas de Navegação</h2>
                <p id="subtituloModalSistemas" class="text-black-400 text-[10px] font-bold uppercase tracking-widest">Sistemas e ferramentas autorizados para seu perfil</p>
            </div>
           
            <!-- Pesquisa tecnológica de sistemas -->
        <div class="pesquisa-sistemas-wrapper mt-3 md:mt-0 md:mr-8">
            <div class="pesquisa-sistemas">
                <span class="pesquisa-sistemas__icone" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="6"></circle>
                        <path d="M16 16L21 21"></path>
                    </svg>
                </span>

                <input
                    type="text"
                    id="inputBuscaSistemas"
                    oninput="filtrarSistemas()"
                    placeholder="Buscar sistema ou módulo..."
                    autocomplete="off"
                >

                <button
                    type="button"
                    class="pesquisa-sistemas__limpar"
                    onclick="limparBuscaSistemas()"
                    title="Limpar pesquisa"
                    aria-label="Limpar pesquisa"
                >
                    &times;
                </button>
            </div>
        </div>

        </div>

        <?php 
            $nav_sistemas_permitidos = [];
            
            // RBAC: Resgata as permissões associadas ao usuário
            if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
                $nav_stmt_sys = $pdo_intra->query("SELECT * FROM sistemas_lista ORDER BY nome");
                $nav_sistemas_permitidos = $nav_stmt_sys->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $nav_stmt_sys = $pdo_intra->prepare("
                    SELECT DISTINCT sl.* FROM sistemas_lista sl
                    LEFT JOIN permissoes_sistemas ps ON sl.id = ps.sistema_id AND ps.user_id = ?
                    LEFT JOIN grupos_sistemas gs ON sl.id = gs.sistema_id
                    LEFT JOIN usuarios_grupos ug ON gs.grupo_id = ug.grupo_id AND ug.usuario_id = ?
                    WHERE ps.user_id IS NOT NULL OR ug.usuario_id IS NOT NULL
                    ORDER BY sl.nome
                ");
                $nav_stmt_sys->execute([$user_id_logado, $user_id_logado]);
                $nav_sistemas_permitidos = $nav_stmt_sys->fetchAll(PDO::FETCH_ASSOC);
            }

            $nav_sistemas_raiz = [];
            $nav_sistemas_filhos = [];

            foreach ($nav_sistemas_permitidos as $nav_sys) {
                if (!empty($nav_sys['pai_id'])) {
                    $nav_sistemas_filhos[$nav_sys['pai_id']][] = $nav_sys;
                } else {
                    $nav_sistemas_raiz[] = $nav_sys;
                }
            }
            // ======================================================
            // PASSO 1 - IDENTIFICA NOC CENTRAL E SISTEMAS RAIZ
            // ======================================================

            $nav_noc_central = null;
            $nav_sistemas_raiz_por_id = [];

            foreach ($nav_sistemas_raiz as $nav_sys) {

                $nav_id_sistema = (int)$nav_sys['id'];

                // ID 52 = NOC CHAMADOS
                if ($nav_id_sistema === 52) {
                    $nav_noc_central = $nav_sys;
                    continue;
                }

                $nav_sistemas_raiz_por_id[$nav_id_sistema] = $nav_sys;
            }

            // ======================================================
            // PASSO 2 - CONFIGURAÇÃO VISUAL DAS CATEGORIAS
            // ======================================================
            //
            // IMPORTANTE:
            // Os sistemas pertencentes a cada categoria NÃO são mais
            // definidos por IDs neste arquivo.
            //
            // A associação agora vem da coluna `categoria`
            // da tabela sistemas_lista.
            //
            // Aqui ficam somente:
            // - nome visual
            // - ícone
            // - cor
            // - ordem de exibição
            // ======================================================

         $nav_categorias_config = [

            'monitoramento' => [
                'nome'  => 'MONITORAMENTO & OBSERVABILIDADE',
                'icone' => '📡'
            ],

            'infraestrutura' => [
                'nome'  => 'INFRAESTRUTURA & CONECTIVIDADE',
                'icone' => '🖥️'
            ],

            'gestao' => [
                'nome'  => 'GESTÃO & PROCESSOS',
                'icone' => '📊'
            ],

            'corporativos' => [
                'nome'  => 'SISTEMAS CORPORATIVOS',
                'icone' => '💼'
            ],

            'facilities' => [
                'nome'  => 'FACILITIES & OPERAÇÃO',
                'icone' => '🏢'
            ],

            'pessoas' => [
                'nome'  => 'PESSOAS & RH',
                'icone' => '👥'
            ],

            'comunicacao' => [
                'nome'  => 'COMUNICAÇÃO & TELEFONIA',
                'icone' => '☎️'
            ],

            'autocorrecao' => [
                'nome'  => 'CORREÇÃO AUTOMÁTICA',
                'icone' => '⚙️'
            ],

            'escalonamento' => [
                'nome'  => 'ABERTURA DE CHAMADO',
                'icone' => '✉️'
            ]

        ];


            // ======================================================
            // PASSO 3 - AGRUPA SISTEMAS PELA CATEGORIA DO BANCO
            // ======================================================

            $nav_categorias_agrupadas = [];

            foreach ($nav_sistemas_raiz as $nav_sys) {

                $nav_id_sistema = (int)$nav_sys['id'];

                // ID 52 continua sendo o NOC central.
                // Ele não pertence a nenhuma categoria lateral.
                if ($nav_id_sistema === 52) {
                    continue;
                }

                $nav_categoria_slug = trim(
                    (string)($nav_sys['categoria'] ?? '')
                );

                // Sistema sem categoria não entra no NOC.
                if ($nav_categoria_slug === '') {
                    continue;
                }

                // Proteção contra uma categoria inválida no banco.
                if (!isset($nav_categorias_config[$nav_categoria_slug])) {
                    continue;
                }

                // Mantém os subitens reais vinculados pelo pai_id.
                $nav_item_sistema = $nav_sys;

                $nav_item_sistema['subitens'] =
                    $nav_sistemas_filhos[$nav_id_sistema] ?? [];

                $nav_categorias_agrupadas[$nav_categoria_slug][] =
                    $nav_item_sistema;
            }


            // ======================================================
            // PASSO 4 - MONTA AS CATEGORIAS RESPEITANDO PERMISSÕES
            // ======================================================

            $nav_categorias = [];

            $nav_sistemas_autocorrecao =
                $nav_categorias_agrupadas['autocorrecao'] ?? [];

            $nav_sistemas_escalonamento =
                $nav_categorias_agrupadas['escalonamento'] ?? [];

            $nav_categorias_saida = [
                'autocorrecao',
                'escalonamento'
            ];

            foreach ($nav_categorias_config as $nav_slug => $nav_config) {

            if (in_array($nav_slug, $nav_categorias_saida, true)) {
                continue;
            }

                $nav_sistemas_categoria =
                    $nav_categorias_agrupadas[$nav_slug] ?? [];

                // Se o usuário não possui nenhum sistema daquela
                // categoria, ela simplesmente não aparece.
                if (empty($nav_sistemas_categoria)) {
                    continue;
                }

                $nav_categorias[] = [
                    'slug'     => $nav_slug,
                    'nome'     => $nav_config['nome'],
                    'icone'    => $nav_config['icone'],
                    'sistemas' => $nav_sistemas_categoria
                ];
            }

        ?>

       <div id="nocEstruturaTeste" style="
            width: 100%;

            display: grid;
            grid-template-columns: 360px 420px 360px;
            gap: 135px;

            align-items: center;
            justify-content: center;

            min-height: 610px;

            padding: 20px 20px;

            position: relative;
            isolation: isolate;
        ">


        <!-- ====================================================== -->
        <!-- CAMADA SVG - CONEXÕES DO NOC -->
        <!-- ====================================================== -->

        <svg
            id="nocLinhasSvg"
            aria-hidden="true"
            style="
                position: absolute;
                inset: 0;

                width: 100%;
                height: 100%;

                pointer-events: none;

                z-index: 0;

                overflow: visible;
            "
        ></svg>

        <!-- ====================================================== -->
        <!-- PASSO 3 - TESTE VISUAL DAS NOVAS CATEGORIAS -->
        <!-- ====================================================== -->

        <div id="nocCategoriasTeste" class="noc-categorias">

            <div class="noc-categorias__titulo">
                ENTRADA
            </div>

            <?php foreach ($nav_categorias as $nav_categoria): ?>

                <?php
                    $nav_categoria_json = htmlspecialchars(
                        json_encode(
                            $nav_categoria['sistemas'],
                            JSON_UNESCAPED_UNICODE |
                            JSON_HEX_APOS |
                            JSON_HEX_QUOT
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>

                <button
                    type="button"
                    class="noc-categoria-btn"
                    data-nome="<?= htmlspecialchars(
                        $nav_categoria['nome'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    data-subitens="<?= $nav_categoria_json; ?>"
                    onclick="abrirCategoriaNoc(this)"
                >

                    <span class="noc-categoria-btn__icone">
                        <?= $nav_categoria['icone']; ?>
                    </span>

                    <span class="noc-categoria-btn__nome">
                        <?= htmlspecialchars(
                            $nav_categoria['nome'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </span>

                    <span class="noc-categoria-btn__seta">
                        ▶
                    </span>

                </button>

            <?php endforeach; ?>

        </div>

<!-- ====================================================== -->
<!-- PASSO 4 - NOC CENTRAL REAL -->
<!-- ====================================================== -->

<div style="
    display: flex;
    align-items: center;
    justify-content: center;
">

    <?php if ($nav_noc_central): ?>

       <a
            href="<?= htmlspecialchars(
                $nav_noc_central['url'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            target="_blank"
            rel="noopener noreferrer"
            id="nocCentralTeste"
            class="noc-sol-souza"
        >

            <div class="noc-sol-rotacao">
                <img src="img/sol_souza.png" alt="Sol Souza">
            </div>

            <div class="noc-sol-miolo">

                <div class="noc-sol-icone">
                    <?= $nav_noc_central['icone']; ?>
                </div>

                <div class="noc-sol-titulo">
                    <?= htmlspecialchars(
                        $nav_noc_central['nome'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>

                <div class="noc-sol-descricao">
                    ACESSO AO NOC DE CHAMADOS
                </div>

            </div>

        </a>

    <?php else: ?>

        <div style="
            width: 300px;
            height: 250px;

            display: flex;
            align-items: center;
            justify-content: center;

            color: rgba(255,255,255,.4);

            clip-path: polygon(
                25% 0%,
                75% 0%,
                100% 50%,
                75% 100%,
                25% 100%,
                0% 50%
            );

            background: rgba(15,23,42,.85);
        ">
            NOC CHAMADOS NÃO LIBERADO
        </div>

    <?php endif; ?>

</div>

   <div id="nocSaidasTeste" style="
        display: flex;
        flex-direction: column;
        gap: 28px;
    ">

    <?php
    $nav_autocorrecao_json = htmlspecialchars(
        json_encode(
            $nav_sistemas_autocorrecao,
            JSON_UNESCAPED_UNICODE |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ),
        ENT_QUOTES,
        'UTF-8'
    );
    ?>

        <!-- AUTOCORREÇÃO -->
        <div
            id="nocSaidaAutocorrecao"
            data-nome="CORREÇÃO AUTOMÁTICA"
            data-subitens="<?= $nav_autocorrecao_json; ?>"
            onclick="abrirCategoriaNoc(this)"
            style="
                min-height: 190px;
                cursor: pointer;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 18px;
                text-align: center;
                border: 3px solid rgba(255, 196, 0, .72);
                border-radius: 14px;
                background:
                    linear-gradient(
                        145deg,
                        rgba(76, 29, 149, .18),
                        rgba(2, 6, 23, .96)
                    );
                box-shadow:
                    0 0 20px rgba(168, 85, 247, .10);
            "
        >

            <div style="font-size: 32px; margin-bottom: 10px;">
                ⚙️
            </div>

            <div style="
                color: #60a5fa;
                font-size: 14px;
                font-weight: 900;
                text-transform: uppercase;
            ">
                AUTOCORREÇÃO
            </div>

            <div style="
                margin-top: 3px;
                color: #c084fc;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
            ">  
                SELF-HEALING
            </div>

            <div style="
                margin-top: 10px;
                color: rgba(255,255,255,.78);
                font-size: 12px;
                font-weight: 700;
                line-height: 1.45;
            ">
                SCRIPTS DE CURA<br>
                EXECUTADOS
            </div>

        </div>

        <?php
        $nav_escalonamento_json = htmlspecialchars(
            json_encode(
                $nav_sistemas_escalonamento,
                JSON_UNESCAPED_UNICODE |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            ),
            ENT_QUOTES,
            'UTF-8'
        );
        ?>


        <!-- ESCALONAMENTO ITSM -->
        <div
            id="nocSaidaItsm"
            data-nome="ABERTURA DE CHAMADO"
            data-subitens="<?= $nav_escalonamento_json; ?>"
            onclick="abrirCategoriaNoc(this)"
            style="
                min-height: 190px;
                cursor: pointer;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 18px;
                text-align: center;
                border: 3px solid rgba(255, 196, 0, .72);
                border-radius: 14px;
                background:
                    linear-gradient(
                        145deg,
                        rgba(76, 29, 149, .18),
                        rgba(2, 6, 23, .96)
                    );
                box-shadow:
                    0 0 20px rgba(168, 85, 247, .10);
            "
        >

            <div style="font-size: 32px; margin-bottom: 10px;">
                ✉️
            </div>

            <div style="
                color: #60a5fa;
                font-size: 14px;
                font-weight: 900;
                text-transform: uppercase;
            ">
                ESCALONAMENTO ITSM
            </div>

            <div style="
                margin-top: 3px;
                color: #c084fc;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
            ">
                TICKET
            </div>

            <div style="
                margin-top: 10px;
                color: rgba(255,255,255,.78);
                font-size: 12px;
                font-weight: 700;
                line-height: 1.45;
            ">
                ABERTURA DE CHAMADO<br>
                COM DIAGNÓSTICO
            </div>

        </div>

    </div>


</div>

<!-- fecha nocEstruturaTeste -->



       <div id="gridSistemasPrincipal" style="display: none;" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-7 max-h-[62vh] overflow-y-auto pr-4 custom-scrollbar-compact animate-in fade-in duration-300">
                <?php if (empty($nav_sistemas_raiz)): ?>
            <div class="col-span-full text-center py-10 text-white/40 text-xs font-bold uppercase tracking-widest">
                ⚠️ NENHUM ACESSO LIBERADO PARA SEU PERFIL.
            </div>
       <?php else: ?>

            <?php
            // Converte as cores cadastradas no banco em cores para o efeito neon
            $nav_cores_neon = [
                'bg-blue-600'    => '#22d3ee',
                'bg-amber-500'   => '#f59e0b',
                'bg-amber-600'   => '#d97706',
                'bg-emerald-500' => '#10b981',
                'bg-emerald-600' => '#059669',
                'bg-purple-500'  => '#a855f7',
                'bg-purple-600'  => '#9333ea',
                'bg-slate-500'   => '#64748b',
                'bg-slate-600'   => '#475569',
                'bg-slate-800'   => '#1e293b',
                'bg-red-500'     => '#ef4444',
                'bg-red-600'     => '#dc2626'
            ];
            ?>

            <?php foreach ($nav_sistemas_raiz as $nav_sys):
                $nav_is_grupo = ($nav_sys['url'] === '#');
                $nav_cor_neon = $nav_cores_neon[$nav_sys['cor']] ?? '#22d3ee';
            ?>

                <?php if ($nav_is_grupo):
                    $nav_sub_json = isset($nav_sistemas_filhos[$nav_sys['id']])
                        ? json_encode(
                            $nav_sistemas_filhos[$nav_sys['id']],
                            JSON_HEX_APOS | JSON_HEX_QUOT
                        )
                        : '[]';
                ?>

                    <div
                        onclick='abrirPastaSistemas(
                            <?php echo json_encode($nav_sys['nome']); ?>,
                            <?php echo $nav_sub_json; ?>
                        )'
                        class="sistema-card sistema-card-neon"
                        style="--neon-cor: <?php echo $nav_cor_neon; ?>;"
                        data-nome="<?php echo strtoupper(htmlspecialchars($nav_sys['nome'], ENT_QUOTES, 'UTF-8')); ?>"
                        data-subitens='<?php echo htmlspecialchars($nav_sub_json, ENT_QUOTES, 'UTF-8'); ?>'
                    >
                        <span class="sistema-card-neon__tipo">Módulo</span>
                        <span class="sistema-card-neon__status"></span>

                        <div class="sistema-card-neon__icone">
                            <?php echo $nav_sys['icone']; ?>
                        </div>

                        <span class="sistema-card-neon__nome">
                            <?php echo htmlspecialchars($nav_sys['nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                <?php else: ?>

                    <a
                        href="<?php echo htmlspecialchars($nav_sys['url'], ENT_QUOTES, 'UTF-8'); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="sistema-card sistema-card-neon"
                        style="--neon-cor: <?php echo $nav_cor_neon; ?>;"
                        data-nome="<?php echo strtoupper(htmlspecialchars($nav_sys['nome'], ENT_QUOTES, 'UTF-8')); ?>"
                    >
                        <span class="sistema-card-neon__tipo">Sistema</span>

                        <div class="sistema-card-neon__icone">
                            <?php echo $nav_sys['icone']; ?>
                        </div>

                        <span class="sistema-card-neon__nome">
                            <?php echo htmlspecialchars($nav_sys['nome'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <div id="gridSistemasSub" class="hidden grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar-compact animate-in slide-in-from-right-5 duration-300"></div>

    </div>
</div>

<?php
// Evita que variáveis internas do módulo vazem para o restante do index.php.
unset(
    $nav_sistemas_permitidos,
    $nav_sistemas_raiz,
    $nav_sistemas_filhos,
    $nav_noc_central,
    $nav_sistemas_raiz_por_id,
    $nav_categorias_config,
    $nav_categorias,
    $nav_sistemas_categoria,
    $nav_item_sistema,
    $nav_stmt_sys,
    $nav_sys,
    $nav_id_sistema,
    $nav_config,
    $nav_slug,
    $nav_categoria,
    $nav_categoria_json,
    $nav_cores_neon,
    $nav_cor_neon,
    $nav_sub_json,
    $nav_is_grupo
);
?>

<style>
        /* =========================================================
        MODAL DE SISTEMAS — VISUAL TECNOLÓGICO NEON
        ========================================================= */

        #modalSistemas {
            background:
                radial-gradient(circle at 15% 20%, rgba(34, 211, 238, 0.12), transparent 30%),
                radial-gradient(circle at 85% 75%, rgba(168, 85, 247, 0.14), transparent 32%),
                rgba(2, 6, 23, 0.82);
        }

            #modalSistemas .modal-sistemas-painel {
            position: relative;

            width: 96vw;
            max-width: 1800px;

            height: 92vh;
            max-height: 950px;

            background-image: url('img/background.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #24529b;

            border: 1px solid rgba(103, 232, 249, 0.22);

            box-shadow:
                0 0 0 1px rgba(168, 85, 247, 0.08),
                0 0 35px rgba(34, 211, 238, 0.10),
                0 30px 80px rgba(0, 0, 0, 0.55);
        }

        #modalSistemas .modal-sistemas-painel.modo-sub {
            height: auto;
        }

        #modalSistemas .modal-sistemas-painel::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            border-radius: inherit;
            background:
                linear-gradient(90deg, transparent 49%, rgba(34, 211, 238, 0.025) 50%, transparent 51%),
                linear-gradient(0deg, transparent 49%, rgba(168, 85, 247, 0.025) 50%, transparent 51%);
            background-size: 40px 40px;
            mask-image: linear-gradient(to bottom, black, transparent 80%);
        }

        #modalSistemas .modal-sistemas-header {
            position: relative;
            z-index: 2;
        }

        #inputBuscaSistemas {
            background: rgba(15, 23, 42, 0.78);
            border: 1px solid rgba(34, 211, 238, 0.20);
            box-shadow: inset 0 0 16px rgba(34, 211, 238, 0.03);
        }

        #inputBuscaSistemas:focus {
            border-color: rgba(34, 211, 238, 0.75);
            box-shadow:
                0 0 0 3px rgba(34, 211, 238, 0.10),
                0 0 22px rgba(34, 211, 238, 0.12);
        }

      /* =========================================================
        CARDS INTERNOS - IDENTIDADE SOUZA
        ========================================================= */

        .sistema-card-neon {
            --neon-cor: #22d3ee;

            position: relative;
            min-height: 150px;
            padding: 18px 12px;
            overflow: hidden;
            isolation: isolate;
            cursor: pointer;
            text-decoration: none;

            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;

            border-radius: 16px;

            /* NOVO PADRÃO SOUZA */
            border: 3px solid rgba(255, 196, 0, .72);

            background:
                linear-gradient(
                    145deg,
                    rgba(8, 31, 72, .97),
                    rgba(5, 22, 53, .97)
                );

            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .05),
                0 10px 25px rgba(0, 25, 70, .24);

            transition:
                transform .25s ease,
                border-color .25s ease,
                box-shadow .25s ease,
                background .25s ease;
        }


        /* LUZ DECORATIVA */
        .sistema-card-neon::before {
            content: "";
            position: absolute;

            width: 100px;
            height: 100px;

            top: -55px;
            right: -50px;

            z-index: -1;

            border-radius: 999px;

            background: #ffc400;

            opacity: .06;
            filter: blur(24px);

            transition: opacity .25s ease;
        }


        /* DETALHE INFERIOR */
        .sistema-card-neon::after {
            content: "";

            position: absolute;

            left: 18%;
            right: 18%;
            bottom: 0;

            height: 1px;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255, 196, 0, .65),
                    transparent
                );

            opacity: .65;
        }


        /* HOVER */
        .sistema-card-neon:hover {
            transform: translateY(-5px);

            border-color: #ff9700;

            background:
                linear-gradient(
                    145deg,
                    rgba(15, 53, 113, .98),
                    rgba(7, 28, 66, .98)
                );

            box-shadow:
                0 0 0 1px rgba(255, 196, 0, .10),
                0 10px 25px rgba(0, 25, 70, .28),
                0 0 18px rgba(255, 151, 0, .12);
        }


        .sistema-card-neon:hover::before {
            opacity: .14;
        }

       /* =========================================================
        ÍCONE - PADRÃO SOUZA
        ========================================================= */

       .sistema-card-neon__icone {
            position: relative;

            width: 72px;
            height: 72px;
            flex-shrink: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 18px;

            border: 1px solid rgba(255, 196, 0, .60);

            background:
                linear-gradient(
                    145deg,
                    rgba(20, 67, 140, .88),
                    rgba(5, 25, 60, .96)
                );

            color: #ffffff;
            font-size: 40px;

            box-shadow:
                inset 0 1px 0 rgba(255,255,255,.06),
                0 4px 12px rgba(0,25,70,.18);

            transition:
                transform .25s ease,
                border-color .25s ease,
                box-shadow .25s ease;
}

        .sistema-card-neon:hover .sistema-card-neon__icone {
            transform: scale(1.08);

            border-color: #ff9700;

            box-shadow:
                0 0 14px rgba(255, 151, 0, .18);
        }

        /* Nome do sistema */
    
        .sistema-card-neon__nome {
            position: relative;
            z-index: 2;

            margin-top: 14px;

            color: rgba(255, 255, 255, .90);

            font-size: 10.5px;
            font-weight: 800;
            line-height: 1.2;

            text-align: center;
            text-transform: uppercase;
            letter-spacing: .025em;

            transition: color .25s ease;
        }

        .sistema-card-neon:hover .sistema-card-neon__nome {
            color: #ffffff;
        }

        /* Indicador das pastas */
        .sistema-card-neon__status {
            position: absolute;

            top: 9px;
            right: 9px;

            width: 7px;
            height: 7px;

            border-radius: 999px;

            background: #ffc400;

            box-shadow:
                0 0 8px rgba(255, 196, 0, .65);
        }

       /* IDENTIFICAÇÃO SISTEMA / MÓDULO */
        .sistema-card-neon__tipo {
            position: absolute;

            top: 8px;
            left: 9px;

            color: #ffffff;
            font-size: 9px; 
            font-weight: 900;

            text-transform: uppercase;
            letter-spacing: .12em;
        }

        /* Ajustes para telas menores */
        @media (max-width: 640px) {
            .sistema-card-neon {
                min-height: 112px;
                padding: 12px 7px;
            }

         .sistema-card-neon__icone {
            width: 58px;
            height: 58px;
            font-size: 32px;
        }

            .sistema-card-neon__nome {
                font-size: 8px;
            }
        }

       /* Rede de conexões entre os cards */
        #gridSistemasPrincipal,
        #gridSistemasSub {
            position: relative;
            isolation: isolate;
        }

        .rede-sistemas-svg {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 0;
            overflow: visible;
            pointer-events: none;
        }
       .rede-sistemas-linha {
            fill: none;
            stroke: #22d3ee;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 7 7;
            opacity: 0.72;
            animation: redeSistemasMovimento 14s linear infinite;
        }

        .rede-sistemas-linha-roxa {
            stroke: #a855f7;
        }

        .rede-sistemas-ponto {
            fill: #67e8f9;
            opacity: 0.95;
        }
      
        #gridSistemasPrincipal > .sistema-card-neon,
        #gridSistemasSub > .sistema-card-neon {
            position: relative;
            z-index: 2;
        }

        @keyframes redeSistemasMovimento {
            from {
                stroke-dashoffset: 0;
            }

            to {
                stroke-dashoffset: -100;
            }
        }

        @media (max-width: 640px) {
            .rede-sistemas-linha {
                opacity: 0.48;
                stroke-width: 2.2;
            }
        }

        /* =========================================================
        PESQUISA TECNOLÓGICA DE SISTEMAS
        ========================================================= */

        .pesquisa-sistemas-wrapper {
            position: relative;
            z-index: 35;
        }

        .pesquisa-sistemas {
            position: relative;
            width: 290px;
            height: 42px;

            display: flex;
            align-items: center;

            border: 1px solid rgba(34, 211, 238, 0.35);
            border-radius: 12px;

            background:
                linear-gradient(145deg, rgba(15, 23, 42, 0.94), rgba(2, 6, 23, 0.94));

            box-shadow:
                inset 0 0 18px rgba(34, 211, 238, 0.035),
                0 0 0 1px rgba(168, 85, 247, 0.03);

            transition:
                border-color 0.25s ease,
                box-shadow 0.25s ease,
                transform 0.25s ease;
        }

        .pesquisa-sistemas:focus-within {
            border-color: rgba(34, 211, 238, 0.9);
            box-shadow:
                0 0 0 3px rgba(34, 211, 238, 0.08),
                0 0 22px rgba(34, 211, 238, 0.15),
                inset 0 0 20px rgba(34, 211, 238, 0.05);
            transform: translateY(-1px);
        }

        .pesquisa-sistemas::after {
            content: "";
            position: absolute;
            left: 18%;
            right: 18%;
            bottom: -1px;
            height: 1px;

            background: linear-gradient(
                90deg,
                transparent,
                #22d3ee,
                #a855f7,
                transparent
            );

            opacity: 0.8;
        }

        .pesquisa-sistemas__icone {
            width: 18px;
            height: 18px;
            margin-left: 13px;
            flex-shrink: 0;
            color: #67e8f9;
        }

        .pesquisa-sistemas__icone svg {
            width: 100%;
            height: 100%;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
        }

        .pesquisa-sistemas input {
            width: 100%;
            height: 100%;
            padding: 0 38px 0 11px;

            color: #f8fafc;
            font-size: 11px;
            font-weight: 600;

            border: none;
            outline: none;
            background: transparent;
        }

        .pesquisa-sistemas input::placeholder {
            color: rgba(148, 163, 184, 0.65);
        }

        .pesquisa-sistemas__limpar {
            position: absolute;
            top: 50%;
            right: 9px;
            transform: translateY(-50%);

            width: 25px;
            height: 25px;

            display: flex;
            align-items: center;
            justify-content: center;

            color: rgba(148, 163, 184, 0.65);
            font-size: 18px;
            line-height: 1;

            border: 1px solid transparent;
            border-radius: 7px;
            background: transparent;

            transition: all 0.2s ease;
        }

        .pesquisa-sistemas__limpar:hover {
            color: white;
            border-color: rgba(168, 85, 247, 0.35);
            background: rgba(168, 85, 247, 0.12);
        }

        @media (max-width: 640px) {
            .pesquisa-sistemas {
                width: 100%;
            }

            .pesquisa-sistemas-wrapper {
                width: 100%;
            }
        }

        #gridSistemasPrincipal,
        #gridSistemasSub {
            overflow-x: hidden !important;
        }

      /* =========================================================
        LINHAS NOC - ENERGIA NEON
        ========================================================= */

        .noc-linha-viva {
            animation: nocLinhaViva 2.2s ease-in-out infinite;
        }

        @keyframes nocLinhaViva {

            0%, 100% {
                opacity: .78;
                filter:
                    drop-shadow(0 0 3px #22d3ee)
                    drop-shadow(0 0 5px rgba(34, 211, 238, .35));
            }

            50% {
                opacity: 1;
                filter:
                    drop-shadow(0 0 6px #22d3ee)
                    drop-shadow(0 0 12px rgba(103, 232, 249, .80));
            }
        }

        /* Quando estiver dentro de uma categoria,
        reserva espaço para o botão VOLTAR */
        #modalSistemasPainel.modo-sub .modal-sistemas-header {
            padding-left: 115px;
        }

        @media (max-width: 640px) {
            #modalSistemasPainel.modo-sub .modal-sistemas-header {
                padding-left: 0;
                padding-top: 45px;
            }
        }

        /* Evita cortar os cards durante a animação de hover */
        #gridSistemasPrincipal,
        #gridSistemasSub {
            padding-top: 10px;
            padding-bottom: 30px;
        }

        /* Garante que o card animado fique acima dos vizinhos */
        #gridSistemasPrincipal > .sistema-card-neon:hover,
        #gridSistemasSub > .sistema-card-neon:hover {
            position: relative;
            z-index: 20;
        }

      /* =========================================================
   PARTE 2 - SOL CENTRAL REAL DA SOUZA
   ========================================================= */

.noc-sol-souza {
    position: relative;

    width: 320px;
    height: 320px;

    display: flex;
    align-items: center;
    justify-content: center;

    text-decoration: none;
    color: white;

    transition: transform .25s ease;
}

.noc-sol-souza:hover {
    transform: scale(1.04);
}


/* SOL REAL DA SOUZA - PARTE QUE GIRA */
.noc-sol-rotacao {
    position: absolute;
    inset: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    animation: girarSolSouza 18s linear infinite;
}

.noc-sol-rotacao img {
    width: 350px;
    height: 350px;

    object-fit: contain;
    display: block;

    user-select: none;
    pointer-events: none;
}


/* CONTEÚDO CENTRAL - FICA PARADO */
.noc-sol-miolo {
    position: relative;
    z-index: 2;

    width: 155px;
    height: 155px;

    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;

    text-align: center;

    padding: 12px;

    /* transparente porque o miolo já existe no PNG */
    background: transparent;
    border: none;
    border-radius: 50%;
    box-shadow: none;
}


/* ÍCONE */
.noc-sol-icone {
    font-size: 25px;
    line-height: 1;
    margin-bottom: 6px;
}


/* NOME DO NOC */
.noc-sol-titulo {
    max-width: 145px;

    color: #164a97;

    font-size: 17px;
    font-weight: 900;
    line-height: 1.05;

    text-transform: uppercase;
}

/* ACESSO AO NOC */
.noc-sol-descricao {
    margin-top: 10px;

    color: #174d9c;

    font-size: 12px;
    font-weight: 800;

    text-transform: uppercase;
}


/* ROTAÇÃO SUAVE */
@keyframes girarSolSouza {

    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }

}

/* =========================================================
   PARTE 3 - CATEGORIAS | IDENTIDADE SOUZA
   ========================================================= */

.noc-categorias {
    max-width: 380px;

    display: flex;
    flex-direction: column;
    gap: 12px;
}

.noc-categorias__titulo {
    margin-bottom: 5px;

    color: #ffffff;

    font-size: 10px;
    font-weight: 900;
    letter-spacing: .18em;

    text-shadow: 0 1px 4px rgba(0, 35, 90, .8);
}


/* BOTÃO */
.noc-categoria-btn {
    width: 100%;
    min-height: 72px;

    display: grid;
    grid-template-columns: 50px 1fr 28px;
    align-items: center;
    gap: 10px;

    padding: 9px 12px;

    color: #ffffff;
    text-align: left;

    border: 3px solid rgba(255, 196, 0, .72);
    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            rgba(8, 31, 72, .96),
            rgba(5, 22, 53, .96)
        );

    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.05),
        0 5px 15px rgba(0,25,70,.22);

    cursor: pointer;

    transition:
        transform .22s ease,
        border-color .22s ease,
        box-shadow .22s ease,
        background .22s ease;
}


/* HOVER */
.noc-categoria-btn:hover {
    transform: translateX(5px);

    border-color: #ff9700;

    background:
        linear-gradient(
            135deg,
            rgba(15, 53, 113, .98),
            rgba(7, 28, 66, .98)
        );

    box-shadow:
        0 7px 20px rgba(0,30,80,.28),
        0 0 14px rgba(255,151,0,.18);
}


/* ÍCONE */
.noc-categoria-btn__icone {
    width: 44px;
    height: 44px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: 1px solid rgba(255,196,0,.30);
    border-radius: 9px;

    background:
        linear-gradient(
            145deg,
            rgba(24,80,160,.80),
            rgba(7,31,75,.95)
        );

    font-size: 24px;
}


/* NOME */
.noc-categoria-btn__nome {
    color: #ffffff;

    font-size: 10px;
    font-weight: 900;
    line-height: 1.2;

    text-transform: uppercase;
}


/* SETA */
.noc-categoria-btn__seta {
    color: #ffc400;

    font-size: 14px;

    transition:
        transform .22s ease,
        color .22s ease;
}

.noc-categoria-btn:hover .noc-categoria-btn__seta {
    color: #ff9700;
    transform: translateX(3px);
}

/* =========================================================
   BOTÃO VOLTAR - MAIS VISÍVEL
   ========================================================= */
    #btnVoltarModal {
        color: #ffffff;

        background:
            linear-gradient(
                135deg,
                #0b1f46,
                #071733
            );

        border: 1px solid #ffc400;

        box-shadow:
            0 5px 14px rgba(0, 20, 60, .30);

        text-shadow: 0 1px 2px rgba(0,0,0,.30);
    }

    #btnVoltarModal:hover {
        color: #ffffff;

        background:
            linear-gradient(
                135deg,
                #164a97,
                #0b2f69
            );

        border-color: #ff9700;

        transform: translateY(-1px) scale(1.03);

        box-shadow:
            0 7px 18px rgba(0, 25, 70, .35),
            0 0 10px rgba(255, 196, 0, .18);
    }

        #subtituloModalSistemas {
            color: #111111 !important;
        }

        /* ANIMAÇÃO DOS CARDS DE SAÍDA DO NOC */
        #nocSaidaAutocorrecao,
        #nocSaidaItsm {
            transition:
                transform .22s ease,
                border-color .22s ease,
                box-shadow .22s ease;
        }

        #nocSaidaAutocorrecao:hover,
        #nocSaidaItsm:hover {
            transform: translateX(-5px);
            border-color: #ff9700 !important;
            box-shadow:
                0 7px 20px rgba(0, 30, 80, .28),
                0 0 18px rgba(255, 151, 0, .22) !important;
        }

</style>

<script>
const SISTEMAS_CORES_NEON = Object.freeze({
    'bg-blue-600': '#22d3ee',
    'bg-amber-500': '#f59e0b',
    'bg-amber-600': '#d97706',
    'bg-emerald-500': '#10b981',
    'bg-emerald-600': '#059669',
    'bg-purple-500': '#a855f7',
    'bg-purple-600': '#9333ea',
    'bg-slate-500': '#64748b',
    'bg-slate-600': '#475569',
    'bg-slate-800': '#1e293b',
    'bg-red-500': '#ef4444',
    'bg-red-600': '#dc2626'
});

    function desenharConexoesSistemas(grid) {
        if (!grid) return;

        // Remove a rede anterior antes de redesenhar
        const redeAnterior = grid.querySelector('.rede-sistemas-svg');

        if (redeAnterior) {
            redeAnterior.remove();
        }

        const cards = Array.from(
            grid.querySelectorAll(':scope > .sistema-card-neon')
        ).filter(card => card.style.display !== 'none');

        if (cards.length < 2) return;

        const estiloGrid = window.getComputedStyle(grid);
        const colunas = estiloGrid.gridTemplateColumns
            .split(' ')
            .filter(Boolean)
            .length;

        const largura = grid.scrollWidth;
        const altura = grid.scrollHeight;

        const svgNS = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(svgNS, 'svg');

        svg.classList.add('rede-sistemas-svg');
        svg.setAttribute('width', largura);
        svg.setAttribute('height', altura);
        svg.setAttribute('viewBox', `0 0 ${largura} ${altura}`);

        const gridRect = grid.getBoundingClientRect();

        function posicaoCard(card) {
            const rect = card.getBoundingClientRect();

            return {
                esquerda: rect.left - gridRect.left + grid.scrollLeft,
                direita: rect.right - gridRect.left + grid.scrollLeft,
                topo: rect.top - gridRect.top + grid.scrollTop,
                base: rect.bottom - gridRect.top + grid.scrollTop,
                centroX: rect.left - gridRect.left + grid.scrollLeft + rect.width / 2,
                centroY: rect.top - gridRect.top + grid.scrollTop + rect.height / 2
            };
        }

        function criarCaminho(d, roxo = false) {
            const path = document.createElementNS(svgNS, 'path');

            path.setAttribute('d', d);
            path.classList.add('rede-sistemas-linha');

            if (roxo) {
                path.classList.add('rede-sistemas-linha-roxa');
            }

            svg.appendChild(path);
        }

        function criarPonto(x, y) {
            const ponto = document.createElementNS(svgNS, 'circle');

            ponto.setAttribute('cx', x);
            ponto.setAttribute('cy', y);
            ponto.setAttribute('r', 3);
            ponto.classList.add('rede-sistemas-ponto');

            svg.appendChild(ponto);
        }

        cards.forEach((card, indice) => {
            const atual = posicaoCard(card);

            // Liga o card ao próximo card da mesma linha
            const existeCardDireita =
                (indice + 1) < cards.length &&
                ((indice + 1) % colunas !== 0);

            if (existeCardDireita) {
                const direita = posicaoCard(cards[indice + 1]);
                const meioX = (atual.direita + direita.esquerda) / 2;

                criarCaminho(
                    `M ${atual.direita} ${atual.centroY}
                    H ${meioX}
                    V ${direita.centroY}
                    H ${direita.esquerda}`,
                    indice % 2 !== 0
                );

                criarPonto(meioX, atual.centroY);
            }

            // Liga o card ao card da linha inferior
            const indiceInferior = indice + colunas;

            if (indiceInferior < cards.length) {
                const inferior = posicaoCard(cards[indiceInferior]);
                const meioY = (atual.base + inferior.topo) / 2;

                criarCaminho(
                    `M ${atual.centroX} ${atual.base}
                    V ${meioY}
                    H ${inferior.centroX}
                    V ${inferior.topo}`,
                    indice % 2 === 0
                );

                criarPonto(atual.centroX, meioY);
            }
        });

        grid.prepend(svg);
    }

function abrirCategoriaNoc(elemento) {

    const nomeCategoria = elemento.getAttribute('data-nome') || 'Categoria';
    const subitensJson = elemento.getAttribute('data-subitens') || '[]';

    let subitens = [];

    try {
        subitens = JSON.parse(subitensJson);
    } catch (erro) {
        console.error('Erro ao carregar os sistemas da categoria:', erro);
        return;
    }

    const estruturaNoc = document.getElementById('nocEstruturaTeste');

    if (estruturaNoc) {
        estruturaNoc.style.display = 'none';
    }

    abrirPastaSistemas(nomeCategoria, subitens);
}

function abrirPastaSistemas(nomePasta, subitens) {

    document
    .getElementById('modalSistemasPainel')
    ?.classList.add('modo-sub');
    const gridPrincipal = document.getElementById('gridSistemasPrincipal');
    const gridSub = document.getElementById('gridSistemasSub');
    const btnVoltar = document.getElementById('btnVoltarModal');
    const titulo = document.getElementById('tituloModalSistemas');
    const subtitulo = document.getElementById('subtituloModalSistemas');

    if (!gridSub) {
        console.error('gridSistemasSub não encontrado no modal.');
        return;
    }

    if (gridPrincipal) {
        gridPrincipal.classList.add('hidden');
        gridPrincipal.style.display = 'none';
    }

    gridSub.classList.remove('hidden');

    if (btnVoltar) {
        btnVoltar.classList.remove('hidden');
    }

    if (titulo) {
        titulo.innerText = nomePasta;
    }

    if (subtitulo) {
        subtitulo.innerText = 'Módulo interno • Aplicações liberadas para seu perfil';
    }

    if (!Array.isArray(subitens) || subitens.length === 0) {
        gridSub.innerHTML = `
            <div class="col-span-full text-center py-12">
                <span class="text-3xl block mb-3">📭</span>
                <p class="text-[10px] text-white/30 font-black uppercase tracking-widest">
                    Nenhuma aplicação vinculada a este módulo.
                </p>
            </div>
        `;
        return;
    }

    gridSub.innerHTML = subitens.map((item, index) => {
        const corNeon = SISTEMAS_CORES_NEON[item.cor] || '#22d3ee';
        const filhos = Array.isArray(item.subitens) ? item.subitens : [];
        const temFilhos = filhos.length > 0;
        const urlItem = typeof item.url === 'string' ? item.url.trim() : '';
        const ehModulo = temFilhos || urlItem === '' || urlItem === '#';
        const nomeSeguro = String(item.nome || 'Sistema');
        const iconeSeguro = item.icone || '🖥️';

        if (ehModulo) {
            return `
                <button
                    type="button"
                    class="sistema-card-neon modulo-interno-noc"
                    data-index="${index}"
                    data-nome="${nomeSeguro.toUpperCase()}"
                    style="--neon-cor: ${corNeon}; width: 100%;"
                >
                    <span class="sistema-card-neon__tipo">Módulo</span>
                    <span class="sistema-card-neon__status"></span>

                    <div class="sistema-card-neon__icone">
                        ${iconeSeguro}
                    </div>

                    <span class="sistema-card-neon__nome">
                        ${nomeSeguro}
                    </span>
                </button>
            `;
        }

        return `
            <a
                href="${urlItem}"
                target="_blank"
                rel="noopener noreferrer"
                class="sistema-card-neon"
                data-nome="${nomeSeguro.toUpperCase()}"
                style="--neon-cor: ${corNeon};"
            >
                <span class="sistema-card-neon__tipo">Sistema</span>

                <div class="sistema-card-neon__icone">
                    ${iconeSeguro}
                </div>

                <span class="sistema-card-neon__nome">
                    ${nomeSeguro}
                </span>
            </a>
        `;
    }).join('');

    gridSub.querySelectorAll('.modulo-interno-noc').forEach(botao => {
        botao.addEventListener('click', function () {
            const index = Number(this.dataset.index);
            const modulo = subitens[index];

            if (!modulo) {
                return;
            }

            const filhosModulo = Array.isArray(modulo.subitens)
                ? modulo.subitens
                : [];

            abrirPastaSistemas(modulo.nome || 'Módulo', filhosModulo);
        });
    });

    requestAnimationFrame(function () {
        desenharConexoesSistemas(gridSub);
    });
}

function exibirPrincipalSistemas() {
    
    document
    .getElementById('modalSistemasPainel')
    ?.classList.remove('modo-sub');
    const estruturaNoc = document.getElementById('nocEstruturaTeste');
    const gridPrincipal = document.getElementById('gridSistemasPrincipal');
    const gridSub = document.getElementById('gridSistemasSub');
    const btnVoltar = document.getElementById('btnVoltarModal');
    const titulo = document.getElementById('tituloModalSistemas');
    const subtitulo = document.getElementById('subtituloModalSistemas');
    const inputBusca = document.getElementById('inputBuscaSistemas');

    if (estruturaNoc) {
        estruturaNoc.style.display = 'grid';
    }

    if (gridPrincipal) {
        gridPrincipal.classList.add('hidden');
        gridPrincipal.style.display = 'none';
    }

    if (gridSub) {
        gridSub.classList.add('hidden');
        gridSub.innerHTML = '';
    }

    if (btnVoltar) {
        btnVoltar.classList.add('hidden');
    }

    if (titulo) {
        titulo.innerText = 'Sistemas de Navegação';
    }

    if (subtitulo) {
        subtitulo.innerText = 'Sistemas e ferramentas autorizados para seu perfil';
    }

    if (inputBusca) {
        inputBusca.value = '';
    }
}

function abrirModalSistemas() {

    const modal = document.getElementById('modalSistemas');

    if (!modal) {
        console.error('modalSistemas não encontrado.');
        return;
    }

    exibirPrincipalSistemas();

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';

    requestAnimationFrame(function () {

    requestAnimationFrame(function () {

        testarLinhaNoc();

    });

});
}

function fecharModalSistemas() {
    const modal = document.getElementById('modalSistemas');

    if (!modal) {
        return;
    }

    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
}


function limparBuscaSistemas() {
    const input = document.getElementById('inputBuscaSistemas');

    input.value = '';
    filtrarSistemas();
    input.focus();

    requestAnimationFrame(function () {
        desenharConexoesSistemas(
            document.getElementById('gridSistemasPrincipal')
        );
    });
}

function normalizarTextoBusca(texto) {
    return String(texto)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toUpperCase()
        .trim();
}

 function filtrarSistemas() {

    const input = document.getElementById('inputBuscaSistemas');
    const gridPrincipal = document.getElementById('gridSistemasPrincipal');
    const gridSub = document.getElementById('gridSistemasSub');
    const estruturaNoc = document.getElementById('nocEstruturaTeste');
    const painel = document.getElementById('modalSistemasPainel');

    if (!input || !gridPrincipal) {
        return;
    }

    const filtro = normalizarTextoBusca(input.value);

    // Remove linhas antigas
    const redeAnterior = gridPrincipal.querySelector('.rede-sistemas-svg');

    if (redeAnterior) {
        redeAnterior.remove();
    }

    // Remove resultados gerados pela pesquisa anterior
    gridPrincipal
        .querySelectorAll('.card-dinamico-busca, .mensagem-busca-vazia')
        .forEach(elemento => elemento.remove());

    // Cards originais
    const cardsOriginais = Array.from(
        gridPrincipal.querySelectorAll(':scope > .sistema-card')
    );

    // =====================================================
    // PESQUISA VAZIA = VOLTA PARA O NOC
    // =====================================================

    if (filtro === '') {

        cardsOriginais.forEach(card => {
            card.style.display = '';
        });

        exibirPrincipalSistemas();

        return;
    }

    // =====================================================
    // ENTROU PESQUISA = ESCONDE NOC E MOSTRA RESULTADOS
    // =====================================================

    if (estruturaNoc) {
        estruturaNoc.style.display = 'none';
    }

    if (gridSub) {
        gridSub.classList.add('hidden');
        gridSub.innerHTML = '';
    }

    gridPrincipal.classList.remove('hidden');
    gridPrincipal.style.display = 'grid';

    // Evita o modal enorme durante a pesquisa
    if (painel) {
        painel.classList.add('modo-sub');
    }

    // Esconde todos antes de filtrar
    cardsOriginais.forEach(card => {
        card.style.display = 'none';
    });

    let totalEncontrado = 0;

    // =====================================================
    // PESQUISA NOS MÓDULOS E SISTEMAS
    // =====================================================

    cardsOriginais.forEach(card => {

        const nomeSistema = normalizarTextoBusca(
            card.getAttribute('data-nome') || ''
        );

        const subitensJson =
            card.getAttribute('data-subitens') || '';

        // Encontrou o próprio módulo/sistema
        if (nomeSistema.includes(filtro)) {

            card.style.display = '';
            totalEncontrado++;

        }

        // Procura também dentro dos módulos
        if (subitensJson) {

            try {

                const subitens = JSON.parse(subitensJson);

                subitens.forEach(sub => {

                    const nomeSubitem =
                        normalizarTextoBusca(sub.nome || '');

                    if (!nomeSubitem.includes(filtro)) {
                        return;
                    }

                    const corNeon =
                        SISTEMAS_CORES_NEON[sub.cor] || '#22d3ee';

                    const novoCard =
                        document.createElement('a');

                    novoCard.href = sub.url;
                    novoCard.target = '_blank';
                    novoCard.rel = 'noopener noreferrer';

                    novoCard.className =
                        'sistema-card-neon card-dinamico-busca';

                    novoCard.style.setProperty(
                        '--neon-cor',
                        corNeon
                    );

                    novoCard.innerHTML = `
                        <span class="sistema-card-neon__tipo">
                            Sistema
                        </span>

                        <div class="sistema-card-neon__icone">
                            ${sub.icone || '🖥️'}
                        </div>

                        <span class="sistema-card-neon__nome">
                            ${sub.nome || 'Sistema'}
                        </span>
                    `;

                    gridPrincipal.appendChild(novoCard);

                    totalEncontrado++;

                });

            } catch (erro) {

                console.error(
                    'Erro ao interpretar os sistemas do módulo:',
                    erro
                );

            }
        }
    });

    // =====================================================
    // NENHUM RESULTADO
    // =====================================================

    if (totalEncontrado === 0) {

        const mensagem = document.createElement('div');

        mensagem.className =
            'mensagem-busca-vazia col-span-full text-center py-12';

        mensagem.innerHTML = `
            <span class="text-3xl block mb-3">🔍</span>

            <p class="text-white/60 text-xs font-black uppercase tracking-widest">
                Nenhum sistema encontrado
            </p>

            <p class="text-white/30 text-[10px] mt-2">
                Tente pesquisar usando outro nome.
            </p>
        `;

        gridPrincipal.appendChild(mensagem);

        return;
    }

    // Redesenha as conexões dos resultados
    requestAnimationFrame(function () {
        desenharConexoesSistemas(gridPrincipal);
    });
}
   

function testarLinhaNoc() {

    const estrutura =
        document.getElementById('nocEstruturaTeste');

    const svg =
        document.getElementById('nocLinhasSvg');

    const categorias =
        Array.from(
            document.querySelectorAll(
                '#nocCategoriasTeste > button'
            )
        );

    const noc =
        document.getElementById('nocCentralTeste');


    if (
        !estrutura ||
        !svg ||
        categorias.length === 0 ||
        !noc
    ) {
        return;
    }


    // =====================================================
    // LIMPA DESENHO ANTERIOR
    // =====================================================

    svg.innerHTML = '';


    const largura =
        estrutura.clientWidth;

    const altura =
        estrutura.clientHeight;


    svg.setAttribute(
        'viewBox',
        `0 0 ${largura} ${altura}`
    );


    const base =
        estrutura.getBoundingClientRect();


    // =====================================================
    // FUNÇÃO PARA PEGAR POSIÇÃO DOS ELEMENTOS
    // =====================================================

    function posicao(elemento) {

        const r =
            elemento.getBoundingClientRect();

        return {

            esquerda:
                r.left - base.left,

            direita:
                r.right - base.left,

            topo:
                r.top - base.top,

            baixo:
                r.bottom - base.top,

            centroX:
                r.left -
                base.left +
                r.width / 2,

            centroY:
                r.top -
                base.top +
                r.height / 2

        };

    }


    const posCategorias =
        categorias.map(posicao);

    const posNoc =
        posicao(noc);


    const svgNS =
        'http://www.w3.org/2000/svg';


    // =====================================================
    // DEFINIÇÕES / SETA
    // =====================================================

    const defs =
        document.createElementNS(
            svgNS,
            'defs'
        );


    const marker =
        document.createElementNS(
            svgNS,
            'marker'
        );

    marker.setAttribute(
        'id',
        'setaEntradaNoc'
    );

    marker.setAttribute(
        'markerWidth',
        '8'
    );

    marker.setAttribute(
        'markerHeight',
        '8'
    );

    marker.setAttribute(
        'refX',
        '7'
    );

    marker.setAttribute(
        'refY',
        '4'
    );

    marker.setAttribute(
        'orient',
        'auto'
    );


    const ponta =
        document.createElementNS(
            svgNS,
            'path'
        );

    ponta.setAttribute(
        'd',
        'M 0 0 L 8 4 L 0 8 Z'
    );

    ponta.setAttribute(
        'fill',
        '#22d3ee'
    );


    marker.appendChild(ponta);

    defs.appendChild(marker);

    svg.appendChild(defs);


    // =====================================================
    // FUNÇÃO PARA CRIAR LINHA
    // =====================================================

        function criarLinha(
            d,
            larguraLinha = 3.5,
            seta = false
        ){

        const path =
            document.createElementNS(
                svgNS,
                'path'
            );


        path.setAttribute(
            'd',
            d
        );

        path.setAttribute(
            'fill',
            'none'
        );

        path.setAttribute(
            'stroke',
            '#22d3ee'
        );

        path.setAttribute(
            'stroke-width',
            larguraLinha
        );

        path.setAttribute(
            'stroke-linecap',
            'round'
        );

        path.setAttribute(
            'stroke-linejoin',
            'round'
        );


        if (seta) {

            path.setAttribute(
                'marker-end',
                'url(#setaEntradaNoc)'
            );

        }
       path.style.filter =
        'drop-shadow(0 0 4px #22d3ee)';

        path.classList.add('noc-linha-viva');

        svg.appendChild(path);


    }


    // =====================================================
    // POSIÇÃO DO TRONCO VERTICAL
    // =====================================================

    const direitaBotoes =
        Math.max(
            ...posCategorias.map(
                p => p.direita
            )
        );


    // Tronco fica no espaço entre botões e NOC
    const xTronco =
        direitaBotoes + 48;


    const primeiroY =
        posCategorias[0].centroY;


    const ultimoY =
        posCategorias[
            posCategorias.length - 1
        ].centroY;


    // =====================================================
    // LINHAS DE CADA CATEGORIA ATÉ O TRONCO
    // =====================================================

    posCategorias.forEach(
        pos => {

            criarLinha(
                `
                M ${pos.direita} ${pos.centroY}
                H ${xTronco}
                `,
                3,
                true
            );

        }
    );


    // =====================================================
    // TRONCO VERTICAL
    // =====================================================

    criarLinha(
        `
        M ${xTronco} ${primeiroY}
        V ${ultimoY}
        `,
        4
    );


    // =====================================================
    // TRONCO → NOC
    // =====================================================

    const indiceCentral =
    Math.floor(posCategorias.length / 2);

    const ySaidaPrincipal =
        posCategorias[indiceCentral].centroY;

        criarLinha(
            `
            M ${xTronco} ${ySaidaPrincipal}
            H ${posNoc.esquerda - 14}
            `,
            4,
            true
        );

    // =====================================================
    // NOC → AUTOCORREÇÃO / ITSM
    // =====================================================

    const auto = document.getElementById('nocSaidaAutocorrecao');
    const itsm = document.getElementById('nocSaidaItsm');

    if (auto && itsm) {

        const pAuto = posicao(auto);
        const pItsm = posicao(itsm);

        const inicioX = posNoc.direita - 55;
        const ramalX = posNoc.direita + 65;

        criarLinha(
            `M ${inicioX} ${posNoc.centroY}
            H ${ramalX}
            V ${pAuto.centroY}
            H ${pAuto.esquerda - 12}`,
            4,
            true
        );

        criarLinha(
            `M ${inicioX} ${posNoc.centroY}
            H ${ramalX}
            V ${pItsm.centroY}
            H ${pItsm.esquerda - 12}`,
            4,
            true
        );
    }

}
</script>