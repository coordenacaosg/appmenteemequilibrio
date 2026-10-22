# Documentação do Sistema — App Mente em Equilíbrio (Gestão de Avaliação Neuropsicológica)

> [!IMPORTANT]
> **Repositório Oficial:** `https://github.com/coordenacaosg/appmenteemequilibrio`
> **Hospedagem Alvo:** Hostinger Web/Cloud Hosting (PHP 8.2+ & MySQL via hPanel com integração Git automática).

---

## 1. Arquitetura e Integração GitHub + Hostinger

O sistema foi projetado para rodar em **Hospedagem Web ou Cloud Compartilhada da Hostinger** (gerenciada via **hPanel**), utilizando o recurso nativo de **Implantação Git (Git Deployment)** da Hostinger conectado diretamente ao repositório `coordenacaosg/appmenteemequilibrio`.

### 1.1. Stack Tecnológica
* **Backend:** PHP 8.2+ (compatível com Apache/LiteSpeed da Hostinger) + PDO MySQL.
* **Banco de Dados:** MySQL 8.0 / MariaDB.
* **Frontend / Interface:** HTML5, TailwindCSS, Alpine.js / Chart.js (para gráficos epidemiológicos).
* **Geração de Documentos:**
  * Geração automática de Contrato preenchido com dados cadastrais e datas das parcelas dos boletos (pronto para impressão/PDF e edição).
  * Geração automática de QR Code para o laudo na etapa de devolutiva.
  * Exportação CSV/Excel para Controle de Nota Fiscal e Relatórios Epidemiológicos.
* **Tarefas Agendadas:** Cron Jobs do hPanel executando verificação automática de prazos (`D+2`, `D+7`, `D-2`).

---

## 2. Perfis de Acesso (RBAC) e LGPD

1. **Administrativo / Secretaria:** CRM de Leads, Agenda, Checklist de Pré-Consulta, Geração de Contratos, Controle de Boletos, Tabela de Nota Fiscal, Pasta Física/Digitalização e Agendamento de Devolutiva.
2. **Equipe Clínica / Neuropsicólogas:** Sessões, aplicação/correção de testes (Pasta Física, Excel Drive, Plataforma VOL), Guia de Análise Qualitativa, checklist de revisão do laudo, Mapa Neuropsicológico, apresentação Gamma, entrevistas (escola/informantes) e lançamento de diagnósticos/comorbidades.
3. **Coordenação / Gisele (Admin Master):** Acesso total + **Fila de Aprovação Exclusiva** (Conferência de Contrato antes do envio e Autorização para Impressão do Laudo) + Painel Gerencial e Epidemiológico.

---

## 3. Etapas Operacionais Mapeadas

1. **Captação e CRM de Leads (WhatsApp):** Registro de contato, origem, idade, queixa/dor principal e automação de remarketing após 7 dias sem conversão.
2. **Pacientes que vão Iniciar (Pré-Consulta):**
   * **3 Itens Externos do Cliente:** Cadastro do Cliente, Anamnese Preliminar e Contrato Assinado (suporte híbrido a Formulário Nativo do sistema e links do Google Forms).
   * **17 Itens Internos:** Agenda, envio de links, cobrança D+2 a D+7, pasta no Drive, informações no laudo, cronograma de sessões, emissão de boletos, geração de contrato com datas dos boletos, **conferência da Gisele**, envio para assinatura, pasta física, envelope de escalas para casa, confirmação 2 dias antes da anamnese e registro na tabela de NF.
3. **Pacientes em Avaliação:**
   * Conferência inicial, lançamento de testes (Pasta Física, Excel Drive, Plataforma VOL), tabela do Guia de Análise Qualitativa, revisão minuciosa do laudo (referências, páginas, tabelas, ortografia, pronomes, datas), **OK da Gisele para imprimir**, Mapa Neuropsicológico, apresentação no Gamma, escaneamento/desocupação da pasta física e registro na tabela estatística.
4. **Pacientes para Devolutiva:**
   * Questionários para pais/escola, entrevista com escola, entrevista com informantes, revisão da anamnese pelos pais/paciente, impressão do laudo com QR Code, preparação da apresentação, agendamento de e-mail com laudo/mapa e realização da devolutiva.
5. **Dupla Visualização:**
   * **Por Cliente:** Progresso individual detalhado e links rápidos.
   * **Por Processos Geral:** Matriz de pendências em lote para atacar gargalos rapidamente.
6. **Painel Epidemiológico (Base 2024–2026):**
   * Estatísticas automáticas por Faixa Etária (`<6 anos`, `6 a 11 anos`, `12 a 17 anos`, `18+ anos`, `50+ anos`), Escola, Escolaridade, Média Mensal e Diagnósticos (`TEA`, `TDAH`, `SD/AH`, `DI`, `TEAp`, `TOD`, `TAS`, `TAG`, etc.).
