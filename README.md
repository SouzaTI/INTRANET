# 🚀 Intranet Navi / Souza - Corporate Portal v1.0

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-00000F?style=for-the-badge&logo=mysql&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

Uma plataforma centralizada de comunicação e gestão corporativa, desenvolvida para integrar as ferramentas do ecossistema Souza, com foco em segurança, experiência do utilizador (UX) e alta performance.

---

## 💎 Funcionalidades Principais

### 💬 Navi Messenger (Chat Corporativo)
Sistema de mensagens em tempo real para comunicação interna segura.
* **Conversas Inteligentes:** Ordenação dinâmica de contactos (WhatsApp-style) colocando as conversas recentes no topo.
* **UX Otimizada:** Divisão entre "Conversas Recentes" e "Outros Contactos" (revelados apenas via busca).
* **Mídia & Som:** Suporte a emojis, notificações sonoras de smartphone e tratamento preciso de fusos horários.
* **Acesso Global:** Chat implementado via modal persistente em todo o portal.

### 🛡️ Centro de Controle RBAC
Matriz de permissões sofisticada para gestão de acessos.
* **Níveis de Permissão:** Gestão granular para Administradores, Gestores de Conteúdo (Docs/Feed) e Administradores de Acessos.
* **Hierarquia de Grupos:** Atribuição de permissões por departamento ou utilizador individual.
* **Audit Logs:** Registo completo de todas as alterações administrativas para conformidade e segurança.

### 🎬 Academia Winthor (Streaming)
Plataforma de treino interno com interface inspirada em serviços de streaming.
* **Playlists Dinâmicas:** Leitura automática de diretórios físicos no servidor.
* **Gestão de Audiência:** Controlo de visualização de vídeos por grupo ou utilizador.

### 📁 Gestão de Documentos (Docs)
Repositório central de ficheiros e manuais.
* **Sincronização de Pastas:** Mapeamento em tempo real de pastas confidenciais.
* **Segurança de Ficheiros:** Filtro de visibilidade baseado na matriz de acessos RBAC.

### 🚀 Launchpad (Sistemas Externos)
Portal de acesso rápido às ferramentas de produtividade.
* **Customização Total:** Admin pode definir nomes, links, ícones (emojis) e cores para cada "bolinha".
* **Fluxo de Trabalho:** Abertura de links em novas abas para manter a Intranet sempre ativa.

---

## 🛠️ Stack Tecnológica

* **Backend:** PHP 8.x (Arquitetura Procedural/Funcional Otimizada)
* **Base de Dados:** MySQL (Integração com base de dados GLPI)
* **Frontend:** HTML5, JavaScript (Vanilla ES6), Tailwind CSS
* **Segurança:** PDO Prepared Statements, Sanitização de Inputs, Controlo de Sessão Seguro.

---

## 📁 Estrutura de Pastas

```text
├── api/                # Motores de busca e processamento de dados (Chat, Acessos)
├── docs/               # Repositório de ficheiros físicos (Documentos)
├── videos/             # Repositório de treinos da Academia Winthor
├── includes/           # Componentes globais (Header, Sidebar, Footer/Chat)
├── config.php          # Configurações de ligação e constantes globais
├── admin_gestao.php    # Painel Administrativo RBAC
└── index.php           # Landing Page e Feed de Notícias
