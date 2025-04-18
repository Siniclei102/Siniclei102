{
  "name": "Ana Miranda",
  "nodes": [
    {
      "parameters": {
        "model": "gpt-4o",
        "options": {}
      },
      "id": "1e4f7f68-3b95-49bc-92be-b5ebf4fb00b7",
      "name": "OpenAI Chat Model1",
      "type": "@n8n/n8n-nodes-langchain.lmChatOpenAi",
      "typeVersion": 1,
      "position": [
        -500,
        1860
      ],
      "credentials": {
        "openAiApi": {
          "id": "MFMsCfDM1RfxIWQq",
          "name": "N8n"
        }
      }
    },
    {
      "parameters": {
        "jsonMode": "expressionData",
        "jsonData": "={{ $json.data || $json.text || $json.concatenated_data }}",
        "options": {
          "metadata": {
            "metadataValues": [
              {
                "name": "=file_id",
                "value": "={{ $('Set File ID').first().json.file_id }}"
              }
            ]
          }
        }
      },
      "id": "1cbc2327-7f73-444b-849b-6649813d3378",
      "name": "Default Data Loader",
      "type": "@n8n/n8n-nodes-langchain.documentDefaultDataLoader",
      "typeVersion": 1,
      "position": [
        -3180,
        2240
      ]
    },
    {
      "parameters": {
        "model": "text-embedding-3-small",
        "options": {}
      },
      "id": "7e7bea1a-039c-4eea-b32e-122b3401401a",
      "name": "Embeddings OpenAI1",
      "type": "@n8n/n8n-nodes-langchain.embeddingsOpenAi",
      "typeVersion": 1,
      "position": [
        -3200,
        2320
      ],
      "credentials": {
        "openAiApi": {
          "id": "MFMsCfDM1RfxIWQq",
          "name": "N8n"
        }
      }
    },
    {
      "parameters": {
        "content": "## Busca Info no RAG",
        "height": 80,
        "width": 250,
        "color": 5
      },
      "id": "cb3db7e9-8fbb-499c-839f-1ace52a50531",
      "name": "Sticky Note",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        -620,
        2020
      ]
    },
    {
      "parameters": {
        "content": "# Ferramenta para Adicionar um Arquivo do Google Drive ao Banco de Dados Vetorial.",
        "height": 80,
        "width": 1493,
        "color": 5
      },
      "id": "2c3f41eb-ed67-460d-a8c7-af046ef8f3ee",
      "name": "Sticky Note1",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        -5040,
        1740
      ]
    },
    {
      "parameters": {
        "operation": "download",
        "fileId": {
          "__rl": true,
          "value": "={{ $json.file_id }}",
          "mode": "id"
        },
        "options": {
          "googleFileConversion": {
            "conversion": {
              "docsToFormat": "text/plain"
            }
          }
        }
      },
      "id": "f80a0299-a07d-4eb6-9e78-66a04756b431",
      "name": "Download File",
      "type": "n8n-nodes-base.googleDrive",
      "typeVersion": 3,
      "position": [
        -4080,
        2020
      ],
      "executeOnce": true,
      "credentials": {
        "googleDriveOAuth2Api": {
          "id": "tIDL9qyHqUNRiaro",
          "name": "Google Drive account"
        }
      }
    },
    {
      "parameters": {
        "pollTimes": {
          "item": [
            {
              "mode": "everyX",
              "value": 12
            }
          ]
        },
        "triggerOn": "specificFolder",
        "folderToWatch": {
          "__rl": true,
          "value": "1PfyI--VpFjr8ikUYPhkyMtOkwWs4HpG6",
          "mode": "list",
          "cachedResultName": "D: ANA MIRANDA",
          "cachedResultUrl": "https://drive.google.com/drive/folders/1PfyI--VpFjr8ikUYPhkyMtOkwWs4HpG6"
        },
        "event": "fileCreated",
        "options": {}
      },
      "id": "3533aac3-a496-47a8-a0ef-c2c6d5e70597",
      "name": "File Created",
      "type": "n8n-nodes-base.googleDriveTrigger",
      "typeVersion": 1,
      "position": [
        -4980,
        1960
      ],
      "credentials": {
        "googleDriveOAuth2Api": {
          "id": "tIDL9qyHqUNRiaro",
          "name": "Google Drive account"
        }
      }
    },
    {
      "parameters": {
        "pollTimes": {
          "item": [
            {
              "hour": 9
            }
          ]
        },
        "triggerOn": "specificFolder",
        "folderToWatch": {
          "__rl": true,
          "value": "1PfyI--VpFjr8ikUYPhkyMtOkwWs4HpG6",
          "mode": "list",
          "cachedResultName": "D: ANA MIRANDA",
          "cachedResultUrl": "https://drive.google.com/drive/folders/1PfyI--VpFjr8ikUYPhkyMtOkwWs4HpG6"
        },
        "event": "fileUpdated",
        "options": {}
      },
      "id": "a0089629-ccd0-4402-b817-f29c9db33383",
      "name": "File Updated",
      "type": "n8n-nodes-base.googleDriveTrigger",
      "typeVersion": 1,
      "position": [
        -4980,
        2160
      ],
      "credentials": {
        "googleDriveOAuth2Api": {
          "id": "tIDL9qyHqUNRiaro",
          "name": "Google Drive account"
        }
      }
    },
    {
      "parameters": {
        "operation": "text",
        "options": {}
      },
      "id": "02ea81d0-8ba9-44ed-baa7-342910f0c7df",
      "name": "Extract Document Text",
      "type": "n8n-nodes-base.extractFromFile",
      "typeVersion": 1,
      "position": [
        -3660,
        2220
      ],
      "alwaysOutputData": true
    },
    {
      "parameters": {
        "model": "text-embedding-3-small",
        "options": {}
      },
      "id": "fb4082be-914d-4e14-8600-b637826b0e32",
      "name": "Embeddings OpenAI",
      "type": "@n8n/n8n-nodes-langchain.embeddingsOpenAi",
      "typeVersion": 1,
      "position": [
        -780,
        1980
      ],
      "credentials": {
        "openAiApi": {
          "id": "MFMsCfDM1RfxIWQq",
          "name": "N8n"
        }
      }
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "id": "10646eae-ae46-4327-a4dc-9987c2d76173",
              "name": "file_id",
              "value": "={{ $json.id }}",
              "type": "string"
            },
            {
              "id": "f4536df5-d0b1-4392-bf17-b8137fb31a44",
              "name": "file_type",
              "value": "={{ $json.mimeType }}",
              "type": "string"
            },
            {
              "id": "c774ed34-0d67-44b7-a537-83810f357b7c",
              "name": "originalFilename",
              "value": "={{ $json.originalFilename }}",
              "type": "string"
            },
            {
              "id": "dff39c85-b4a2-45ba-a5ff-f4b311999efc",
              "name": "sha1Checksum",
              "value": "={{ $json.sha1Checksum }}",
              "type": "string"
            }
          ]
        },
        "options": {}
      },
      "id": "91bc84ae-b186-482a-a402-d30b35e49b46",
      "name": "Set File ID",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -4800,
        2000
      ]
    },
    {
      "parameters": {
        "content": "# Agente IA",
        "height": 80,
        "width": 276,
        "color": 5
      },
      "id": "060c04cd-fb56-4d22-8b32-8af64b46fb12",
      "name": "Sticky Note2",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        -820,
        1100
      ]
    },
    {
      "parameters": {
        "operation": "pdf",
        "options": {}
      },
      "id": "5c9702c4-9353-49bc-9b78-d266545a12c0",
      "name": "Extract PDF Text",
      "type": "n8n-nodes-base.extractFromFile",
      "typeVersion": 1,
      "position": [
        -3660,
        1840
      ]
    },
    {
      "parameters": {
        "aggregate": "aggregateAllItemData",
        "options": {}
      },
      "id": "f4366383-60e6-48dc-a280-fa0ac3d05769",
      "name": "Aggregate",
      "type": "n8n-nodes-base.aggregate",
      "typeVersion": 1,
      "position": [
        -3520,
        2020
      ]
    },
    {
      "parameters": {},
      "id": "6b8f96fe-9036-41de-8ad5-cacae49fbe49",
      "name": "Character Text Splitter",
      "type": "@n8n/n8n-nodes-langchain.textSplitterCharacterTextSplitter",
      "typeVersion": 1,
      "position": [
        -3020,
        2380
      ]
    },
    {
      "parameters": {
        "fieldsToSummarize": {
          "values": [
            {
              "aggregation": "concatenate",
              "field": "data"
            }
          ]
        },
        "options": {}
      },
      "id": "1cbe42c3-c2f7-462b-8a59-b38afb9092b6",
      "name": "Summarize",
      "type": "n8n-nodes-base.summarize",
      "typeVersion": 1,
      "position": [
        -3380,
        2020
      ]
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.file_type }}",
                    "rightValue": "application/pdf",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              }
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "id": "2ae7faa7-a936-4621-a680-60c512163034",
                    "leftValue": "={{ $json.file_type }}",
                    "rightValue": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                    "operator": {
                      "type": "string",
                      "operation": "equals",
                      "name": "filter.operator.equals"
                    }
                  }
                ],
                "combinator": "and"
              }
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "id": "fc193b06-363b-4699-a97d-e5a850138b0e",
                    "leftValue": "={{ $json.file_type }}",
                    "rightValue": "application/vnd.google-apps.document",
                    "operator": {
                      "type": "string",
                      "operation": "equals",
                      "name": "filter.operator.equals"
                    }
                  }
                ],
                "combinator": "and"
              }
            }
          ]
        },
        "options": {
          "fallbackOutput": 2
        }
      },
      "id": "4a92051f-e7d8-469f-9bf6-7f53c2e51ea8",
      "name": "Switch",
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3,
      "position": [
        -3900,
        2020
      ]
    },
    {
      "parameters": {
        "mode": "insert",
        "tableName": {
          "__rl": true,
          "value": "documents",
          "mode": "list",
          "cachedResultName": "documents"
        },
        "options": {
          "queryName": "match_documents"
        }
      },
      "id": "835b422b-c090-4d75-82e9-a90120521549",
      "name": "Insert into Supabase Vectorstore",
      "type": "@n8n/n8n-nodes-langchain.vectorStoreSupabase",
      "typeVersion": 1,
      "position": [
        -3200,
        2020
      ],
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "tableName": {
          "__rl": true,
          "value": "documents",
          "mode": "list",
          "cachedResultName": "documents"
        },
        "options": {
          "queryName": "match_documents"
        }
      },
      "id": "7439ea5e-5236-41c4-b516-df15bd5a9e4f",
      "name": "Supabase Vector Store",
      "type": "@n8n/n8n-nodes-langchain.vectorStoreSupabase",
      "typeVersion": 1,
      "position": [
        -800,
        1860
      ],
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "xlsx",
        "options": {}
      },
      "id": "1bcf375d-b419-429e-b996-a7ea9377ed31",
      "name": "Extract from Excel",
      "type": "n8n-nodes-base.extractFromFile",
      "typeVersion": 1,
      "position": [
        -3660,
        2020
      ]
    },
    {
      "parameters": {
        "content": "",
        "height": 620,
        "width": 820,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -840,
        1080
      ],
      "typeVersion": 1,
      "id": "c4eab880-de82-44c4-9681-df20b46a84ee",
      "name": "Sticky Note4"
    },
    {
      "parameters": {
        "content": "",
        "height": 400,
        "width": 480,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -840,
        1720
      ],
      "typeVersion": 1,
      "id": "6f155f1f-4f64-47aa-903f-81b619cd4216",
      "name": "Sticky Note5"
    },
    {
      "parameters": {
        "content": "",
        "height": 800,
        "width": 2180,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -5060,
        1720
      ],
      "typeVersion": 1,
      "id": "afd05c66-a3b6-474e-8aae-cdde63bcf6fb",
      "name": "Sticky Note6"
    },
    {
      "parameters": {
        "content": "## Arquivos criados em uma pasta específica > Verificar o tipo de arquivo e realizar conversão > Extrair o texto > Adicionar ao Vector Store",
        "height": 80,
        "width": 1600,
        "color": 5
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -5040,
        2420
      ],
      "typeVersion": 1,
      "id": "88a06c89-c2e3-4d3b-824b-54382632454d",
      "name": "Sticky Note12"
    },
    {
      "parameters": {
        "content": "## Gatilho de Monitoramento",
        "height": 480,
        "width": 213,
        "color": 5
      },
      "id": "a3fffb89-d28e-48f8-a2d9-c5aa95a6ec7a",
      "name": "Sticky Note10",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        -5020,
        1860
      ]
    },
    {
      "parameters": {
        "operation": "get",
        "tableId": "dados_cliente",
        "filters": {
          "conditions": [
            {
              "keyName": "telefone",
              "keyValue": "={{ $('Webhook EVO').item.json.body.data.key.remoteJid }}"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.supabase",
      "typeVersion": 1,
      "position": [
        -3580,
        1440
      ],
      "id": "1256d380-c63e-4c79-9f2d-d2524f7acd73",
      "name": "Supabase",
      "alwaysOutputData": true,
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "conditions": {
          "options": {
            "caseSensitive": true,
            "leftValue": "",
            "typeValidation": "strict",
            "version": 2
          },
          "conditions": [
            {
              "id": "4a6d9aac-8565-4c58-abe3-8741393a5535",
              "leftValue": "={{ $json.telefone }}",
              "rightValue": "",
              "operator": {
                "type": "string",
                "operation": "exists",
                "singleValue": true
              }
            }
          ],
          "combinator": "and"
        },
        "options": {}
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 2.2,
      "position": [
        -3400,
        1440
      ],
      "id": "f5b94817-29b6-48b8-b759-7cc76b5906fd",
      "name": "If1"
    },
    {
      "parameters": {
        "action": "generate"
      },
      "type": "n8n-nodes-base.crypto",
      "typeVersion": 1,
      "position": [
        -3280,
        1540
      ],
      "id": "586ed5d8-3209-42de-877c-37b5977fc50d",
      "name": "Gerar sessionID"
    },
    {
      "parameters": {
        "tableId": "dados_cliente",
        "fieldsUi": {
          "fieldValues": [
            {
              "fieldId": "sessionid",
              "fieldValue": "={{ $json.data }}"
            },
            {
              "fieldId": "telefone",
              "fieldValue": "={{ $('Webhook EVO').item.json.body.data.key.remoteJid }}"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.supabase",
      "typeVersion": 1,
      "position": [
        -3140,
        1540
      ],
      "id": "0c57ebe2-96ba-4188-a994-31748aded341",
      "name": "Supabase1",
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "options": {}
      },
      "id": "365f51ae-f600-4ade-826b-f4f7a29b84e8",
      "name": "OpenAI3",
      "type": "@n8n/n8n-nodes-langchain.lmChatOpenAi",
      "typeVersion": 1,
      "position": [
        200,
        860
      ],
      "credentials": {
        "openAiApi": {
          "id": "MFMsCfDM1RfxIWQq",
          "name": "N8n"
        }
      }
    },
    {
      "parameters": {
        "options": {}
      },
      "id": "e13b322c-5662-4763-96bf-e71ec9829990",
      "name": "Loop Over Items3",
      "type": "n8n-nodes-base.splitInBatches",
      "typeVersion": 3,
      "position": [
        640,
        680
      ]
    },
    {
      "parameters": {
        "schemaType": "manual",
        "inputSchema": "{\n  \"type\": \"object\",\n  \"properties\": {\n    \"messages\": {\n      \"type\": \"array\",\n      \"items\": {\n        \"type\": \"string\"\n      }\n    }\n  },\n  \"required\": [\"messages\"]\n}"
      },
      "id": "97308e8a-d4c5-498e-b327-70509ac1c894",
      "name": "OutputParser1",
      "type": "@n8n/n8n-nodes-langchain.outputParserStructured",
      "typeVersion": 1.2,
      "position": [
        440,
        860
      ]
    },
    {
      "parameters": {
        "promptType": "define",
        "text": "=Whatsapp message to be splitted and formated: {{ $json.output }}",
        "hasOutputParser": true,
        "messages": {
          "messageValues": [
            {
              "message": "=Por favor, gere a saída no seguinte formato JSON:\n{\n  \"messages\": [\n    \"splitedMessage\",\n    \"splitedMessage\",\n    \"splitedMessage\"\n  ]\n}\n\nAs mensagens devem ser divididas de forma natural, afinal estamos conversando com um humano, não é mesmo?\n\nCertifique-se de que a resposta siga exatamente essa estrutura, incluindo os colchetes e as aspas.\n\n### Jamais separe uma mensagem vazia.\n\n### Certifique-se de que a resposta siga exatamente essa estrutura abaixo, deixando somente entre '*' para negrito e nunca fugindo das demais regras de markdown do whatsapp:\n\t\t\t- *negrito* (substitua '**' por '*')\n\t\t\t- ~tachado~ (caso seja algo que foi excluído ou alterado)\n\t\t\t- _itálico_.(extremamente raro)\n            - `link` (usar sempre em todos os links)\n\nTudo o que for link, pode colocar entre \"`\", ou seja, na seguinte formatação: `www.link.com.br`\n"
            }
          ]
        }
      },
      "id": "7c7e18e2-190c-42b0-ad53-8488094b4cfb",
      "name": "Parser  Chain",
      "type": "@n8n/n8n-nodes-langchain.chainLlm",
      "typeVersion": 1.4,
      "position": [
        180,
        680
      ]
    },
    {
      "parameters": {
        "content": "",
        "height": 620,
        "width": 1100,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -1960,
        1080
      ],
      "typeVersion": 1,
      "id": "92d66c5d-1cf4-448e-891d-af125642074d",
      "name": "Sticky Note17"
    },
    {
      "parameters": {
        "content": "",
        "height": 560,
        "width": 1180,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        0,
        400
      ],
      "typeVersion": 1,
      "id": "b1037a2c-3c08-41d7-abf5-00f13e352c1d",
      "name": "Sticky Note18"
    },
    {
      "parameters": {
        "content": "# Divisão de Mensagens Inteligente - Texto",
        "height": 80,
        "width": 736,
        "color": 5
      },
      "id": "ac6bca64-3826-492d-9230-cca0a967549e",
      "name": "Sticky Note19",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        20,
        420
      ]
    },
    {
      "parameters": {
        "content": "",
        "height": 620,
        "width": 880,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -2860,
        1080
      ],
      "typeVersion": 1,
      "id": "f4467db6-183f-4c64-bd19-1e50cb85cc51",
      "name": "Sticky Note20"
    },
    {
      "parameters": {
        "content": "",
        "height": 440,
        "width": 740,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -3620,
        1260
      ],
      "typeVersion": 1,
      "id": "77005be4-3ff6-47b8-8284-b6f9157e3f13",
      "name": "Sticky Note22"
    },
    {
      "parameters": {
        "content": "# Gera/Consulta sessionId",
        "height": 80,
        "width": 596,
        "color": 5
      },
      "id": "e7f24972-313a-4d41-92bc-4bf8757bf905",
      "name": "Sticky Note23",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        -3600,
        1280
      ]
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "id": "0985904e-7d4e-4fba-ae26-2c44200855e1",
                    "leftValue": "={{ $('Webhook EVO').item.json[\"body\"][\"data\"][\"messageType\"] }}",
                    "rightValue": "imageMessage",
                    "operator": {
                      "type": "string",
                      "operation": "equals",
                      "name": "filter.operator.equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "imagem"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "id": "52aaf749-fe4f-44e4-880e-15b2bfc027f1",
                    "leftValue": "={{ $('Webhook EVO').item.json[\"body\"][\"data\"][\"messageType\"] }}",
                    "rightValue": "extendedTextMessage",
                    "operator": {
                      "type": "string",
                      "operation": "equals",
                      "name": "filter.operator.equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "text"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "id": "e514e613-fd6a-48bd-b0ae-bae2448c810e",
                    "leftValue": "={{ $('Webhook EVO').item.json[\"body\"][\"data\"][\"messageType\"] }}",
                    "rightValue": "conversation",
                    "operator": {
                      "type": "string",
                      "operation": "equals",
                      "name": "filter.operator.equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "text"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "leftValue": "={{ $('Webhook EVO').item.json[\"body\"][\"data\"][\"messageType\"] }}",
                    "rightValue": "audioMessage",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "audio"
            }
          ]
        },
        "options": {}
      },
      "id": "93e8c6d6-a6a6-4509-9c76-48a66adb96aa",
      "name": "Switch3",
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3,
      "position": [
        -180,
        1140
      ]
    },
    {
      "parameters": {
        "content": "",
        "height": 440,
        "width": 780,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -4800,
        1260
      ],
      "typeVersion": 1,
      "id": "932c0a1f-c4ac-4ed8-b555-636f98e3bf58",
      "name": "Sticky Note26"
    },
    {
      "parameters": {
        "options": {}
      },
      "type": "@n8n/n8n-nodes-langchain.lmChatOpenAi",
      "typeVersion": 1,
      "position": [
        -800,
        1480
      ],
      "id": "b4aab0e6-0c6f-4da0-adb5-f2c4ed888142",
      "name": "OpenAI Chat Model",
      "credentials": {
        "openAiApi": {
          "id": "MFMsCfDM1RfxIWQq",
          "name": "N8n"
        }
      }
    },
    {
      "parameters": {
        "jsCode": "// Obtém os valores dos nós anteriores\nconst sessionid1 = $input.item.json.data;  // Do nó \"Gerar sessionID\"\nconst sessionid2 = $input.item.json.sessionid;  // Do nó \"Supabase\"\n\n// Retorna apenas o que existir, chamando sempre de \"sessionId\"\nreturn [{ sessionId: sessionid1 || sessionid2 || null }];\n"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        -3020,
        1380
      ],
      "id": "c8b8d92e-7f24-4fbf-8123-953af5849ade",
      "name": "Code1"
    },
    {
      "parameters": {
        "promptType": "define",
        "text": "={{ $json.mensagem_completa }}",
        "options": {
          "systemMessage": "={\n  \"DataAtual\": \"{{ $now.weekdayLong }},{{ $now.format('dd/MM/yyyy') }},{{ $now.hour.toString().padStart(2, '0') }}:{{ $now.minute.toString().padStart(2, '0') }}\",\n  \"Agent\": {\n    \"nome\": \"Ísis\",\n    \"personalidade\": \"Secretária vendedora, fala de forma simples usando as regras gramaticais do português brasileiro, empática, acolhedora e respeitadora da dor dos outros, chamado o cliente sempre pelo nome, elogiando suas iniciativa, nunca enviar emoji e fazendo uma pergunta por vez.\",\n    \"Empresa\": \"Dra.Ana Miranda Terapia e Saúde Emocional\",\n    \"objetivo\": \"Acolher o lead, entendendo seu problema e convertendo da maneira mais eficiente possível o lead em cliente de terapia.\",\n    \"Regras_universais\": \" regra 1: Faça perguntas de forma individual para a conversa mais fluida como se fosse uma conversa presencial entre 2 humanos, Regra 2: caso seja a primeira interação e não tenha histórico de conversa se apresente como Ísis secretaria da dr ana \"\n  },\n  \"regra_para_encaminhamento_de_agendamento\": {\n    \"descricao\": \"Para realizar o encaminhamento para agendamento da sessão inicial com a Dra. Ana Miranda, é necessário coletar as seguintes respostas do leade apos coletar informe como funciona e que vai passar para a dra ana agendar:\",\n    \"respostas_necessarias\": [\n      {\n        \"item\": \"Nome do lead\",\n        \"acao\": \"Se apresentar e perguntar: 'Olá, me chamo Ísis e faço parte da equipe da Dra. Ana Miranda. Obrigada pelo contato! Qual é o seu nome?'\"\n      },\n      {\n        \"item\": \"Como podemos ajudar\",\n        \"acao\": \"Perguntar: 'Feliz pelo seu contato. Qual desafio você está enfrentando neste momento? '\"\n      },\n      {\n        \"item\": \"Impacto do problema no dia a dia\",\n        \"acao\": \"Perguntar: 'E como isso tem afetado seu dia a dia? Quais dificuldades você sente por conta disso?'\"\n      },\n      {\n        \"item\": \"Experiência prévia com terapia\",\n        \"acao\": \"Perguntar: 'Você já fez terapia antes ou essa será sua primeira vez?' Caso já tenha feito, pergunte: 'Como foi essa experiência para você?'\"\n      },\n      {\n        \"item\": \"Confirmação de interesse na sessão inicial\",\n        \"acao\": \"Apresentar a sessão e perguntar: 'A Dra. Ana realiza uma sessão inicial de 30 minutos por R$50, online e sigilosa, para entender suas necessidades e criar um plano personalizado. Esse valor é revertido como desconto se continuar o processo terapêutico. Quer agendar essa sessão?'\"\n      }\n    ],\n    \"finalizacao\": \"Após coletar todas as respostas acima e o lead confirmar o interesse, informe que vai encaminhar para a Dra ana agendar: 'Ótima decisão, Talvez a Dra. Ana consiga realizar sua sessão inicial amanhã ou depois de amanhã. Vou confirmar com ela e te informo assim que tiver a resposta. Seu protocolo é 221701.' Se houver objeção ao pagamento, pergunte o motivo, quebre a objeção com empatia e reapresente a oferta.\"\n  },\n  \"chamadas_para_acao\": {\n    \"descricao\": \"Quando o lead fizer perguntas sobre a Dra. Ana Miranda ou o funcionamento dos serviços, após responder, use uma dessas chamadas para ação para direcionar ao agendamento ou aprofundar a conversa:\",\n    \"exemplos\": [\n      \"Vamos agendar sua sessão inicial para que a Dra. Ana possa te ajudar com isso?\",\n      \"Podemos continuar com o agendamento para você começar a resolver essa questão?\",\n      \"Posso saber mais sobre seu caso para te encaminhar da melhor forma?\",\n    ],\n    \"instrucao\": \"Escolha a chamada mais adequada ao contexto da pergunta do lead, sempre mantendo o tom acolhedor e incentivando a próxima etapa.\"\n  },\n  \n  ],\n  \"regras_de_atuacao\": [\n    {\n      \"Regras\": [\n        \"Fornecer somente informações sobre os serviços da Ana Miranda e o que está na base de conhecimento.\",\n        \"Redirecionar para um representante humano se algo estiver fora do escopo ou exigir questões clínicas mais complexas.\",\n        \"Não fornecer diagnósticos ou conselhos médicos.\",\n        \"Sempre usar 'processo terapêutico' em vez de 'tratamento' em todas as respostas.\",\n\"Nunca fale prazer em te conhecer fale: feliz em te conhecer a palavra prazer não te boa conotação\",\n\"Não marque agenda, você semrpe informa que vai verificar com a dra. ana para ela realizar o agendamento\",\n\"Se a pessoa pedir para falar com a dra.ana avise que vai passar o recado que ela vai entrar em contato em breve\"\n\"Use a tool: \"Buscar_documentos\" quando o cliente fizer uma pergunta que não está descrita aqui no prompt para ter informações para uma resposta coerente\"\n      ]\n    }\n  ],\n  \n}"
        }
      },
      "id": "59af0762-123e-41df-a901-bdb611e4432a",
      "name": "Atendente",
      "type": "@n8n/n8n-nodes-langchain.agent",
      "typeVersion": 1.6,
      "position": [
        -660,
        1280
      ]
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -4160,
        1040
      ],
      "typeVersion": 1,
      "id": "98c3e007-f7fe-49a1-aa05-b9c4309c9061",
      "name": "Sticky Note31"
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -3640,
        820
      ],
      "typeVersion": 1,
      "id": "0db24085-5422-410a-8f9d-a124f080056c",
      "name": "Sticky Note33"
    },
    {
      "parameters": {
        "options": {}
      },
      "type": "n8n-nodes-base.splitInBatches",
      "typeVersion": 3,
      "position": [
        -4260,
        2000
      ],
      "id": "ed012e85-4328-41fd-841a-427f079be1a0",
      "name": "Loop Over Items"
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -3120,
        1040
      ],
      "typeVersion": 1,
      "id": "494b0ea6-891c-405f-8e24-d7c3d7564453",
      "name": "Sticky Note37"
    },
    {
      "parameters": {
        "sessionIdType": "customKey",
        "sessionKey": "={{ $('Code1').item.json.sessionId }}",
        "contextWindowLength": 100
      },
      "type": "@n8n/n8n-nodes-langchain.memoryPostgresChat",
      "typeVersion": 1.3,
      "position": [
        -680,
        1480
      ],
      "id": "9d6dc94f-0f61-469e-8e29-10b6167593d0",
      "name": "Postgres Chat Memory",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "AnaMiranda",
        "options": {}
      },
      "id": "01db02c0-d885-4ad5-89fa-d18066e82495",
      "name": "Webhook EVO",
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 2,
      "position": [
        -4980,
        1440
      ],
      "webhookId": "51a23d5e-4849-4931-84b5-52c29cca0ecf"
    },
    {
      "parameters": {
        "content": "# Pausa para Atendimento Humano",
        "height": 80,
        "width": 656,
        "color": 5
      },
      "id": "80015951-20fc-4e43-851e-b58d61f8107d",
      "name": "Sticky Note41",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        -4780,
        1280
      ]
    },
    {
      "parameters": {
        "content": "",
        "height": 440,
        "width": 360,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -4000,
        1260
      ],
      "typeVersion": 1,
      "id": "3251b555-441e-4b04-b803-535da101d05c",
      "name": "Sticky Note42"
    },
    {
      "parameters": {
        "content": "# Dados",
        "height": 80,
        "width": 150,
        "color": 5
      },
      "id": "06f28772-2426-45c9-812f-9b4b3178a8e3",
      "name": "Sticky Note43",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        -3980,
        1280
      ]
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 2
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.output }}",
                    "rightValue": "221701",
                    "operator": {
                      "type": "string",
                      "operation": "notContains"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Envia para Atendente"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 2
                },
                "conditions": [
                  {
                    "id": "820760d6-3546-4007-8917-55d366a74261",
                    "leftValue": "={{ $json.output }}",
                    "rightValue": "221701",
                    "operator": {
                      "type": "string",
                      "operation": "contains"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Transfere para Atendente"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3.2,
      "position": [
        -320,
        1320
      ],
      "id": "eb4e42cd-a991-4f7c-8f26-2e9f2e83d166",
      "name": "Switch2"
    },
    {
      "parameters": {
        "options": {}
      },
      "type": "@n8n/n8n-nodes-langchain.lmChatOpenAi",
      "typeVersion": 1,
      "position": [
        1560,
        1600
      ],
      "id": "1adbe325-d317-4a83-ae95-893800650d0a",
      "name": "OpenAI Chat Model2",
      "credentials": {
        "openAiApi": {
          "id": "MFMsCfDM1RfxIWQq",
          "name": "N8n"
        }
      }
    },
    {
      "parameters": {
        "content": "",
        "height": 400,
        "width": 2200,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        0,
        1300
      ],
      "typeVersion": 1,
      "id": "a62c2ab0-2c2b-46aa-bdbf-08483272b395",
      "name": "Sticky Note44"
    },
    {
      "parameters": {
        "content": "# AVISAR NOVO LEAD NO GRUPO",
        "height": 80,
        "width": 656,
        "color": 5
      },
      "id": "4269d451-f533-4ee5-aad4-a8953a419bce",
      "name": "Sticky Note45",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        20,
        1320
      ]
    },
    {
      "parameters": {
        "resource": "messages-api",
        "instanceName": "Ana Miranda",
        "remoteJid": "={{ $('Variáveis').item.json.telefone }}",
        "messageText": "={{ $json.output }}",
        "options_message": {
          "delay": 4200,
          "linkPreview": false
        }
      },
      "type": "n8n-nodes-evolution-api.evolutionApi",
      "typeVersion": 1,
      "position": [
        860,
        700
      ],
      "id": "3debe9bf-c527-4d89-8d57-1f9554d7bdb2",
      "name": "Evolution API",
      "credentials": {
        "evolutionApi": {
          "id": "70jgQAs0Osw6bvvF",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "resource": "messages-api",
        "instanceName": "Ana Miranda",
        "remoteJid": "={{ $('Variáveis').item.json.telefone }}",
        "messageText": "={{ $json.output }}",
        "options_message": {}
      },
      "type": "n8n-nodes-evolution-api.evolutionApi",
      "typeVersion": 1,
      "position": [
        80,
        1420
      ],
      "id": "d4a3c726-e671-4eec-8c40-8a2a942253bd",
      "name": "Evolution API1",
      "credentials": {
        "evolutionApi": {
          "id": "70jgQAs0Osw6bvvF",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "resource": "messages-api",
        "instanceName": "Ana Miranda",
        "remoteJid": "120363383659037085@g.us",
        "messageText": "=🚨 Novo Lead: wa.me/{{ $('Variáveis').item.json.telefone.split('@')[0] }} 🚨\n Cliente: {{ $('Webhook EVO').item.json.body.data.pushName }}\n\nCASO:\n{{ $json.text }}",
        "options_message": {}
      },
      "type": "n8n-nodes-evolution-api.evolutionApi",
      "typeVersion": 1,
      "position": [
        1940,
        1420
      ],
      "id": "3be3bd83-4aa0-49f6-9f89-6a2f17c4bbd6",
      "name": "Evolution API2",
      "credentials": {
        "evolutionApi": {
          "id": "70jgQAs0Osw6bvvF",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {},
      "type": "n8n-nodes-base.merge",
      "typeVersion": 3,
      "position": [
        660,
        180
      ],
      "id": "f312011e-07a8-49c0-a08f-35755051a4c2",
      "name": "Merge1"
    },
    {
      "parameters": {
        "conditions": {
          "options": {
            "caseSensitive": true,
            "leftValue": "",
            "typeValidation": "strict",
            "version": 2
          },
          "conditions": [
            {
              "id": "defac08a-6745-422b-bb05-90da9f47d91b",
              "leftValue": "={{ $('Busca Telefone').last().json.values() }}",
              "rightValue": "",
              "operator": {
                "type": "array",
                "operation": "empty",
                "singleValue": true
              }
            }
          ],
          "combinator": "and"
        },
        "options": {}
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 2.2,
      "position": [
        200,
        140
      ],
      "id": "6230f728-30a7-4902-be04-22284ea57493",
      "name": "If4"
    },
    {
      "parameters": {
        "content": "",
        "height": 380,
        "width": 1180,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        0,
        0
      ],
      "typeVersion": 1,
      "id": "43f881a6-e0b3-47a3-b95e-1765cbb57fe6",
      "name": "Sticky Note54"
    },
    {
      "parameters": {
        "content": "# Cadastra Chat Supabase",
        "height": 80,
        "width": 450,
        "color": 5
      },
      "id": "11b103fb-fc6c-4948-b428-dfe114a81f47",
      "name": "Sticky Note55",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        20,
        20
      ]
    },
    {
      "parameters": {
        "resource": "messages-api",
        "instanceName": "Ana Miranda",
        "remoteJid": "={{ $('Variáveis').item.json.telefone }}",
        "messageText": "={{ $json.output }}",
        "options_message": {
          "delay": 1200
        }
      },
      "type": "n8n-nodes-evolution-api.evolutionApi",
      "typeVersion": 1,
      "position": [
        200,
        520
      ],
      "id": "1faa4d74-292a-43b9-9685-29a9dd929b24",
      "name": "Evolution API5",
      "credentials": {
        "evolutionApi": {
          "id": "70jgQAs0Osw6bvvF",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "formatDate",
        "date": "={{ $now }}",
        "format": "custom",
        "customFormat": "dd-MM-yyyy",
        "options": {}
      },
      "type": "n8n-nodes-base.dateTime",
      "typeVersion": 2,
      "position": [
        -3780,
        1440
      ],
      "id": "368827b0-4c35-4074-8f1c-b8c035342a0a",
      "name": "Date & Time1"
    },
    {
      "parameters": {
        "operation": "get",
        "tableId": "chats",
        "filters": {
          "conditions": [
            {
              "keyName": "phone",
              "keyValue": "={{ $('Webhook EVO').item.json.body.data.key.remoteJid }}"
            },
            {
              "keyName": "app",
              "keyValue": "drana"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.supabase",
      "typeVersion": 1,
      "position": [
        60,
        140
      ],
      "id": "30674a32-6d39-4f11-a814-b4a1ac4ef955",
      "name": "Busca Telefone",
      "alwaysOutputData": true,
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      },
      "onError": "continueRegularOutput"
    },
    {
      "parameters": {
        "tableId": "chats",
        "fieldsUi": {
          "fieldValues": [
            {
              "fieldId": "phone",
              "fieldValue": "={{ $('Webhook EVO').item.json.body.data.key.remoteJid }}"
            },
            {
              "fieldId": "updated_at",
              "fieldValue": "={{ $now}}"
            },
            {
              "fieldId": "conversation_id",
              "fieldValue": "={{ $('Code1').item.json.sessionId }}"
            },
            {
              "fieldId": "app",
              "fieldValue": "drana"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.supabase",
      "typeVersion": 1,
      "position": [
        440,
        80
      ],
      "id": "f1555c40-c510-48ff-a01a-c0b44e7e0025",
      "name": "Adiciona CHAT supabase",
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "update",
        "tableId": "chats",
        "filters": {
          "conditions": [
            {
              "keyName": "phone",
              "condition": "eq",
              "keyValue": "={{ $('Variáveis').item.json.telefone }}"
            }
          ]
        },
        "fieldsUi": {
          "fieldValues": [
            {
              "fieldId": "updated_at",
              "fieldValue": "={{ $now }}"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.supabase",
      "typeVersion": 1,
      "position": [
        440,
        240
      ],
      "id": "684764ff-f9f7-4185-ad10-8bd1333685d7",
      "name": "Atualiza CHAT Supabase",
      "alwaysOutputData": true,
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "tableId": "chat_messages",
        "fieldsUi": {
          "fieldValues": [
            {
              "fieldId": "phone",
              "fieldValue": "={{ $json.phone }}"
            },
            {
              "fieldId": "conversation_id",
              "fieldValue": "={{ $json.phone }}"
            },
            {
              "fieldId": "bot_message",
              "fieldValue": "={{ $('Atendente').item.json.output }}"
            },
            {
              "fieldId": "user_message",
              "fieldValue": "={{ $('Variáveis').item.json.mensagem }}"
            },
            {
              "fieldId": "message_type",
              "fieldValue": "={{ $('Variáveis').item.json.body.event }}"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.supabase",
      "typeVersion": 1,
      "position": [
        840,
        180
      ],
      "id": "eb34875c-9583-4c68-add2-4ad4ac851aaa",
      "name": "Cria Histórico Supabase",
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "conditions": {
          "options": {
            "caseSensitive": true,
            "leftValue": "",
            "typeValidation": "strict",
            "version": 2
          },
          "conditions": [
            {
              "id": "7c4cdb12-9452-42a6-a39a-c268bd38dce1",
              "leftValue": "={{ $json.output.lenght }}",
              "rightValue": 90,
              "operator": {
                "type": "number",
                "operation": "gt"
              }
            }
          ],
          "combinator": "and"
        },
        "options": {}
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 2.2,
      "position": [
        40,
        600
      ],
      "id": "ebc03678-810f-46af-86c5-d8f01907cfbc",
      "name": "Menos que 240 Caracteres"
    },
    {
      "parameters": {
        "fieldToSplitOut": "output.messages",
        "options": {
          "destinationFieldName": "output"
        }
      },
      "id": "346f6d0e-96a1-4efa-99c2-fcb74c62fa76",
      "name": "Split de Mensagem",
      "type": "n8n-nodes-base.splitOut",
      "typeVersion": 1,
      "position": [
        480,
        680
      ]
    },
    {
      "parameters": {
        "amount": 1
      },
      "id": "b2ce54cf-7289-4606-bcec-d8c19e8a2077",
      "name": "1 segundo",
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1.1,
      "position": [
        1000,
        700
      ],
      "webhookId": "48d9eabe-321b-4602-a3f8-91508c54779a"
    },
    {
      "parameters": {
        "jsCode": "return $items(\"Set File ID\").map(item => {\n  return {\n    json: {\n      file_id: item.json.file_id,\n      file_type: item.json.file_type\n    }\n  };\n});"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        -4440,
        2000
      ],
      "id": "79a11179-a682-4844-a284-39cba310b02e",
      "name": "Retorna ID do arquivo"
    },
    {
      "parameters": {
        "operation": "delete",
        "tableId": "documents",
        "filterType": "string",
        "filterString": "=metadata->>file_id=like.*{{ $json.file_id }}*"
      },
      "id": "1d4c5cc9-24a3-43d8-90b9-fc0b495fe03a",
      "name": "Deleta linhas antigas do documento",
      "type": "n8n-nodes-base.supabase",
      "typeVersion": 1,
      "position": [
        -4640,
        2000
      ],
      "alwaysOutputData": true,
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "create table dados_cliente (\n  id bigserial primary key,\n  created_at TIMESTAMPTZ, \n  telefone text, \n  sessionid text\n);",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -4100,
        840
      ],
      "id": "bcb15816-33c3-4c3a-a5f9-7f51e78410d8",
      "name": "Cria Tabela Dados Cliente",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "-- Create a table to store your documents\ncreate table documents (\n  id bigserial primary key,\n  content text, -- corresponds to Document.pageContent\n  metadata jsonb, -- corresponds to Document.metadata\n  embedding vector(1536) -- 1536 works for OpenAI embeddings, change if needed\n);",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -3060,
        840
      ],
      "id": "b0d64de5-226d-47ff-8663-9f994261f1e0",
      "name": "Cria Tabela Documentos",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "delete from documents",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -3060,
        1060
      ],
      "id": "f2a69d45-5d82-46c5-a46a-75b1335b6045",
      "name": "Deleta Conteúdo Documentos",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -3380,
        820
      ],
      "typeVersion": 1,
      "id": "415a32af-703b-4bf2-a60e-76ae83479dd7",
      "name": "Sticky Note40"
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -3120,
        820
      ],
      "typeVersion": 1,
      "id": "d849c823-c957-40dc-aff7-f53efe1e7982",
      "name": "Sticky Note48"
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -4420,
        820
      ],
      "typeVersion": 1,
      "id": "db7d876e-4a6b-4a45-a5f6-d947a690e466",
      "name": "Sticky Note49"
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -3900,
        820
      ],
      "typeVersion": 1,
      "id": "efc5221d-a92c-4dce-8719-facb9f04e3a2",
      "name": "Sticky Note50"
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -3640,
        1040
      ],
      "typeVersion": 1,
      "id": "a6391fa9-270e-4816-b8bc-4cfa746a8716",
      "name": "Sticky Note56"
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -4160,
        820
      ],
      "typeVersion": 1,
      "id": "fe2e0c05-d6aa-48b9-8040-0e14bfba1f55",
      "name": "Sticky Note57"
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "delete from n8n_chat_histories",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -4100,
        1080
      ],
      "id": "c70c48af-589d-492b-8c8d-5e7146184e0f",
      "name": "Deleta Histórico",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "create function match_documents (\n  query_embedding vector(1536),\n  match_count int default null,\n  filter jsonb DEFAULT '{}'\n) returns table (\n  id bigint,\n  content text,\n  metadata jsonb,\n  similarity float\n)\nlanguage plpgsql\nas $$\n#variable_conflict use_column\nbegin\n  return query\n  select\n    id,\n    content,\n    metadata,\n    1 - (documents.embedding <=> query_embedding) as similarity\n  from documents\n  where metadata @> filter\n  order by documents.embedding <=> query_embedding\n  limit match_count;\nend;\n$$;",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -3320,
        840
      ],
      "id": "a798c0d9-f50e-4d7b-a6b5-0affbb25e869",
      "name": "Cria função Busca em Vetor",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "create extension vector;",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -3580,
        840
      ],
      "id": "38b74606-6243-42cf-a850-49bc09f89068",
      "name": "Cria Extensão Vetor",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "create table chats (\n  id bigserial primary key,\n  created_at TIMESTAMPTZ, \n  phone text,\n  updated_at text, \n  app text,\n  conversation_id text\n);",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -3840,
        840
      ],
      "id": "25633f33-19a9-4d19-a93a-65e5b9e8a667",
      "name": "Cria Tabela Chats",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -3380,
        1040
      ],
      "typeVersion": 1,
      "id": "b844ca04-bbbe-46c2-a5be-498846a8c544",
      "name": "Sticky Note65"
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "delete from chats",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -3320,
        1060
      ],
      "id": "702efd21-b0df-4635-9340-1ae801e6c5f4",
      "name": "Deleta Conteúdo Chats",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "delete from dados_cliente",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -3580,
        1060
      ],
      "id": "71afda21-0478-449c-9cbf-570f282a175a",
      "name": "Deleta Conteúdo Dados Cliente",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "content": "",
        "height": 200
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -3900,
        1040
      ],
      "typeVersion": 1,
      "id": "ed4759ea-a525-4ecd-bd71-a562f922c8d2",
      "name": "Sticky Note66"
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "delete from chat_messages",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -3840,
        1060
      ],
      "id": "a60e3ba9-e1c1-4789-ba0e-1106d11bcf62",
      "name": "Deleta Conteúdo Chat_Messages",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "executeQuery",
        "query": "CREATE TABLE chat_messages (\n  id BIGSERIAL PRIMARY KEY,\n  created_at TIMESTAMPTZ, \n  phone TEXT,\n  conversation_id TEXT, \n  bot_message TEXT,\n  user_message TEXT, \n  message_type TEXT,\n  active BOOLEAN DEFAULT TRUE\n);\n",
        "options": {}
      },
      "type": "n8n-nodes-base.postgres",
      "typeVersion": 2.5,
      "position": [
        -4360,
        840
      ],
      "id": "13034b21-66a4-49af-8e8f-65d50e34d0eb",
      "name": "Cria Tabela Chat_Messages",
      "credentials": {
        "postgres": {
          "id": "U12rYBNMAbvg3CUV",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "content": "",
        "height": 440,
        "color": 4
      },
      "type": "n8n-nodes-base.stickyNote",
      "position": [
        -5060,
        1260
      ],
      "typeVersion": 1,
      "id": "47deab44-ab22-4ce6-884a-e4558202cb83",
      "name": "Sticky Note32"
    },
    {
      "parameters": {
        "content": "# Webhook",
        "height": 100,
        "width": 196,
        "color": 5
      },
      "id": "23bcbe50-87a4-4bb1-a532-d7a12e81e833",
      "name": "Sticky Note67",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        -5040,
        1280
      ]
    },
    {
      "parameters": {
        "operation": "toBinary",
        "sourceProperty": "data",
        "options": {
          "fileName": "file.png",
          "mimeType": "image/png"
        }
      },
      "id": "beae25c5-04bf-4b20-bc36-5806d53f807c",
      "name": "Convert to File1",
      "type": "n8n-nodes-base.convertToFile",
      "typeVersion": 1.1,
      "position": [
        -2660,
        1540
      ]
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "id": "3c5ccbc9-69d1-4b13-a7c3-e6945bc8c655",
              "name": "data",
              "value": "={{ $('Webhook EVO').item.json.body.data.message.base64 }}",
              "type": "string"
            }
          ]
        },
        "options": {}
      },
      "id": "e348aa43-86d2-40d8-a7d2-a0d9fd8ea9de",
      "name": "Edit Fields3",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -2800,
        1540
      ]
    },
    {
      "parameters": {
        "conditions": {
          "options": {
            "caseSensitive": true,
            "leftValue": "",
            "typeValidation": "strict",
            "version": 1
          },
          "conditions": [
            {
              "id": "ade56c50-1520-4760-8df5-e99617d6d3ad",
              "leftValue": "={{ $('Webhook EVO').item.json.body.data.message.imageMessage.caption }}",
              "rightValue": "",
              "operator": {
                "type": "string",
                "operation": "empty",
                "singleValue": true
              }
            }
          ],
          "combinator": "and"
        },
        "options": {}
      },
      "id": "5d636ae2-f483-4e54-a5ed-378427b21703",
      "name": "If3",
      "type": "n8n-nodes-base.if",
      "typeVersion": 2,
      "position": [
        -2360,
        1540
      ]
    },
    {
      "parameters": {
        "operation": "push",
        "list": "={{ $('Code1').item.json.sessionId }}",
        "messageData": "={{ $('Webhook EVO').item.json[\"body\"][\"data\"][\"message\"][\"imageMessage\"][\"caption\"] }}, {{ $json.content.replace(/\\n/g, \"\\\\n\").replace(/['\"]/g, '').trim()  }}",
        "tail": true
      },
      "id": "9844df7d-f1ab-4a6d-b64b-d8b9a8e066dd",
      "name": "Redis4",
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        -2180,
        1540
      ],
      "credentials": {
        "redis": {
          "id": "SWzSXUhno9OVr2z8",
          "name": "Redis account"
        }
      }
    },
    {
      "parameters": {
        "operation": "push",
        "list": "={{ $('Code1').item.json.sessionId }}",
        "messageData": "={{ $json.content.replace(/\\n/g, \"\\\\n\").replace(/['\"]/g, '').trim()  }}",
        "tail": true
      },
      "id": "17283755-867b-4caf-a5e9-91f165c89963",
      "name": "Redis5",
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        -2180,
        1400
      ],
      "credentials": {
        "redis": {
          "id": "SWzSXUhno9OVr2z8",
          "name": "Redis account"
        }
      }
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "id": "52aaf749-fe4f-44e4-880e-15b2bfc027f1",
                    "leftValue": "={{ $('Webhook EVO').item.json.body.data.messageType }}",
                    "rightValue": "extendedTextMessage",
                    "operator": {
                      "type": "string",
                      "operation": "equals",
                      "name": "filter.operator.equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "text"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "id": "e514e613-fd6a-48bd-b0ae-bae2448c810e",
                    "leftValue": "={{ $('Webhook EVO').item.json.body.data.messageType }}",
                    "rightValue": "conversation",
                    "operator": {
                      "type": "string",
                      "operation": "equals",
                      "name": "filter.operator.equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "text"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "leftValue": "={{ $('Webhook EVO').item.json.body.data.messageType }}",
                    "rightValue": "audioMessage",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "audio"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "id": "c0e434dd-1268-421d-b81b-3a5e90ed9550",
                    "leftValue": "={{ $('Webhook EVO').item.json.body.data.messageType }}",
                    "rightValue": "imageMessage",
                    "operator": {
                      "type": "string",
                      "operation": "equals",
                      "name": "filter.operator.equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "image"
            }
          ]
        },
        "options": {}
      },
      "id": "b211d4d4-a4c3-4adf-8110-0f57d657cfde",
      "name": "Switch1",
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3,
      "position": [
        -2800,
        1300
      ]
    },
    {
      "parameters": {
        "content": "# Mensagem Picotada",
        "height": 80,
        "width": 396,
        "color": 5
      },
      "id": "3c18547e-36cc-4811-8c7d-6676aae61663",
      "name": "Sticky Note3",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        -1600,
        1120
      ]
    },
    {
      "parameters": {
        "resource": "image",
        "operation": "analyze",
        "modelId": {
          "__rl": true,
          "value": "gpt-4o-mini",
          "mode": "list",
          "cachedResultName": "GPT-4O-MINI"
        },
        "text": "Resumo curto da imagem. Responda sem acento, sem hifens",
        "inputType": "base64",
        "options": {}
      },
      "id": "aec86590-d0e9-4592-b0db-26f065504d4f",
      "name": "OpenAI1",
      "type": "@n8n/n8n-nodes-langchain.openAi",
      "typeVersion": 1.6,
      "position": [
        -2500,
        1540
      ],
      "credentials": {
        "openAiApi": {
          "id": "MFMsCfDM1RfxIWQq",
          "name": "N8n"
        }
      }
    },
    {
      "parameters": {
        "conditions": {
          "options": {
            "caseSensitive": true,
            "leftValue": "",
            "typeValidation": "strict",
            "version": 2
          },
          "conditions": [
            {
              "id": "d5a342e9-585b-42ea-be44-644adae10199",
              "leftValue": "={{ $json.Redis2 }}",
              "rightValue": "={{ $json.Redis1 }}",
              "operator": {
                "type": "string",
                "operation": "equals"
              }
            }
          ],
          "combinator": "and"
        },
        "options": {}
      },
      "id": "3faaa821-a87a-4faf-8116-eb57a199cd0f",
      "name": "Compara Get Memory1",
      "type": "n8n-nodes-base.if",
      "typeVersion": 2.2,
      "position": [
        -1260,
        1340
      ]
    },
    {
      "parameters": {
        "operation": "push",
        "list": "={{ $('Code1').item.json.sessionId }}",
        "messageData": "={{ $('Webhook EVO').item.json.body.data.message.conversation }}",
        "tail": true
      },
      "id": "04a0e4f4-9712-4aaa-98cb-bad3491fe17e",
      "name": "Text Memory1",
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        -2380,
        1100
      ],
      "credentials": {
        "redis": {
          "id": "SWzSXUhno9OVr2z8",
          "name": "Redis account"
        }
      }
    },
    {
      "parameters": {
        "operation": "push",
        "list": "={{ $('Code1').item.json.sessionId }}",
        "messageData": "={{ $('Webhook EVO').item.json.body.data.message.speechToText }}",
        "tail": true
      },
      "id": "cd97be50-74cb-4f9d-ab21-3018aed28f31",
      "name": "Audio Memory1",
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        -2580,
        1340
      ],
      "credentials": {
        "redis": {
          "id": "SWzSXUhno9OVr2z8",
          "name": "Redis account"
        }
      }
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "id": "f336a1ff-e577-489d-a739-1eb8bd509245",
              "name": "Redis2",
              "value": "={{ $json.propertyName }}",
              "type": "string"
            },
            {
              "id": "946d1420-e379-46e3-8fcd-3816340fbabb",
              "name": "Redis1",
              "value": "={{ $('Get Memory 1').item.json.propertyName }}",
              "type": "string"
            }
          ]
        },
        "options": {}
      },
      "id": "ffd67248-da60-4b11-b955-2285e4b31ff4",
      "name": "Edit Fields8",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -1440,
        1340
      ]
    },
    {
      "parameters": {
        "amount": 50
      },
      "id": "89945475-045e-4074-9b06-4f9ea4d921a6",
      "name": "Wait3",
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1.1,
      "position": [
        -1760,
        1340
      ],
      "webhookId": "7508fa49-bc87-45fc-bc55-e92f0d00664a"
    },
    {
      "parameters": {
        "operation": "get",
        "key": "={{ $('Code1').item.json.sessionId }}",
        "options": {}
      },
      "id": "189887ea-8215-4835-b6a0-2b7c88455401",
      "name": "Get Memory 1",
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        -1920,
        1340
      ],
      "credentials": {
        "redis": {
          "id": "SWzSXUhno9OVr2z8",
          "name": "Redis account"
        }
      }
    },
    {
      "parameters": {
        "operation": "get",
        "key": "={{ $('Code1').item.json.sessionId }}",
        "options": {}
      },
      "id": "66b09fb2-500d-44ff-87dd-726a4107ea5a",
      "name": "Get Memory 2",
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        -1600,
        1340
      ],
      "credentials": {
        "redis": {
          "id": "SWzSXUhno9OVr2z8",
          "name": "Redis account"
        }
      }
    },
    {
      "parameters": {
        "operation": "delete",
        "key": "={{ $('Code1').item.json.sessionId }}"
      },
      "id": "29570316-af5d-46d5-9c5f-8955378633e3",
      "name": "Delete Memory",
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        1020,
        180
      ],
      "credentials": {
        "redis": {
          "id": "SWzSXUhno9OVr2z8",
          "name": "Redis account"
        }
      }
    },
    {
      "parameters": {
        "name": "buscar_documentos",
        "description": "Contains all the information about prices and andress that you can check to answer user questions."
      },
      "id": "e20b1c3c-a61a-4e4b-b064-74370a895aab",
      "name": "buscar_documentos",
      "type": "@n8n/n8n-nodes-langchain.toolVectorStore",
      "typeVersion": 1,
      "position": [
        -720,
        1740
      ]
    },
    {
      "parameters": {
        "operation": "get",
        "tableId": "dados_cliente",
        "filters": {
          "conditions": [
            {
              "keyName": "telefone",
              "keyValue": "={{ $json.telefone }}"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.supabase",
      "typeVersion": 1,
      "position": [
        -3180,
        1380
      ],
      "id": "df4d0930-c929-4e79-9d80-c9cc66810f5f",
      "name": "Get Dados",
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "id": "8d88c137-383f-4307-b3cc-1f6a560ea67b",
              "name": "telefone",
              "value": "={{ $('Webhook EVO').item.json.body.data.key.remoteJid }}",
              "type": "string"
            },
            {
              "id": "7e2f520e-4952-425b-82ca-792cc46680d4",
              "name": "mensagem",
              "value": "={{ $('Webhook EVO').item.json.body.data.message.conversation }}{{ $('Webhook EVO').item.json.body.data.message.extendedTextMessage.text }}",
              "type": "string"
            },
            {
              "id": "de1388b1-a6e4-42df-a6a5-7e9b4594f97d",
              "name": "body.event",
              "value": "={{ $('Webhook EVO').item.json.body.event }}",
              "type": "string"
            }
          ]
        },
        "options": {}
      },
      "id": "e9285c5e-587c-473f-940b-c0dfa1be72e8",
      "name": "Variáveis",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -3940,
        1440
      ]
    },
    {
      "parameters": {
        "assignments": {
          "assignments": [
            {
              "id": "8f16b1bf-1a3e-4029-8d7a-1bccb919ee43",
              "name": "message.message_id",
              "value": "={{ $('Webhook EVO').item.json.body.data.key.id || '' }}",
              "type": "string"
            },
            {
              "id": "11800d83-ecca-4f9c-a878-a2419db0c8e9",
              "name": "message.chat_id",
              "value": "={{ $('Webhook EVO').item.json.body.data.key.remoteJid || '' }}",
              "type": "string"
            },
            {
              "id": "c33f9527-e661-49e5-8e5e-64f3b430928a",
              "name": "message.content_type",
              "value": "={{ $('Webhook EVO').item.json.body.data.message.extendedTextMessage ? 'text' : '' }}{{ $('Webhook EVO').item.json.body.data.message.conversation ? 'text' : '' }}{{ $('Webhook EVO').item.json.body.data.message.audioMessage ? 'audio' : '' }}{{ $('Webhook EVO').item.json.body.data.message.imageMessage ? 'image' : '' }}",
              "type": "string"
            },
            {
              "id": "06eba1c9-cff0-4f68-b6da-6bb0092466b7",
              "name": "message.content",
              "value": "={{ $('Webhook EVO').item.json.body.data.message.extendedTextMessage?.text || '' }}{{ $('Webhook EVO').item.json.body.data.message.imageMessage?.caption || '' }}{{ $('Webhook EVO').item.json.body.data.message.conversation || '' }}",
              "type": "string"
            },
            {
              "id": "b97f1af3-5361-46fc-9303-d644921231d8",
              "name": "message.timestamp",
              "value": "={{ $('Webhook EVO').item.json.body.data.messageTimestamp.toDateTime('s').toISO() }}",
              "type": "string"
            },
            {
              "id": "dc3dc59c-90a3-4a45-bea2-de092c91083b",
              "name": "message.content_url",
              "value": "={{ $('Webhook EVO').item.json.body.data.message.audioMessage?.url || '' }}{{ $('Webhook EVO').item.json.body.data.message.imageMessage?.url || '' }}",
              "type": "string"
            },
            {
              "id": "8b01a818-a456-476e-bace-adefe2f04eb4",
              "name": "message.event",
              "value": "={{ $('Webhook EVO').item.json.body.data.key.fromMe ? 'outcoming' : 'incoming' }}",
              "type": "string"
            },
            {
              "id": "b2f1f6b5-292f-4695-9e41-be200c6d7053",
              "name": "instance.name",
              "value": "={{ $json.body.instance }}",
              "type": "string"
            },
            {
              "id": "572fcce5-8a26-4e8f-a48a-ef0bee569dcd",
              "name": "instance.apikey",
              "value": "={{ $json.body.apikey }}",
              "type": "string"
            },
            {
              "id": "e90043db-657b-461c-b040-2d6089abfbdb",
              "name": "instance.server_url",
              "value": "={{ $json.body.server_url }}",
              "type": "string"
            }
          ]
        },
        "options": {}
      },
      "id": "ce7cb20a-95f3-4d76-867d-58b33e4a93d8",
      "name": "dados_para_atendimento_humano1",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.4,
      "position": [
        -4720,
        1440
      ]
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.block }}",
                    "rightValue": "",
                    "operator": {
                      "type": "string",
                      "operation": "empty",
                      "singleValue": true
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Ia Ativa"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "id": "3ef0e01c-cc14-4663-bb4d-2905b350c3ab",
                    "leftValue": "={{ $json.block }}",
                    "rightValue": "true",
                    "operator": {
                      "type": "string",
                      "operation": "equals",
                      "name": "filter.operator.equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "Ia Desativada"
            }
          ]
        },
        "options": {}
      },
      "id": "b9ecca34-ba7d-4cf1-8f95-37db0381a955",
      "name": "Switch Block",
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3,
      "position": [
        -4180,
        1540
      ]
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "leftValue": "={{ $json.message.event }}",
                    "rightValue": "outcoming",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "outcoming"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 1
                },
                "conditions": [
                  {
                    "id": "d7b42536-638f-4128-b51b-6aa913e9d9bc",
                    "leftValue": "={{ $json.message.event }}",
                    "rightValue": "incoming",
                    "operator": {
                      "type": "string",
                      "operation": "equals",
                      "name": "filter.operator.equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "incoming"
            }
          ]
        },
        "options": {}
      },
      "id": "0fc844ec-8b38-4ffb-b438-d439cc5c96e0",
      "name": "Switch6",
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3,
      "position": [
        -4540,
        1440
      ]
    },
    {
      "parameters": {
        "operation": "getAll",
        "tableId": "chats",
        "filters": {
          "conditions": [
            {
              "keyName": "app",
              "condition": "eq",
              "keyValue": "drana"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.supabase",
      "typeVersion": 1,
      "position": [
        340,
        1420
      ],
      "id": "ae5e66f2-976c-4f87-b1b9-d55693fefee0",
      "name": "ListChats-Supabase2",
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "operation": "getAll",
        "tableId": "chat_messages",
        "matchType": "allFilters",
        "filters": {
          "conditions": [
            {
              "keyName": "phone",
              "condition": "eq",
              "keyValue": "={{ $('Webhook EVO').item.json.body.data.key.remoteJid }}"
            },
            {
              "keyName": "active",
              "condition": "eq",
              "keyValue": "true"
            }
          ]
        }
      },
      "type": "n8n-nodes-base.supabase",
      "typeVersion": 1,
      "position": [
        860,
        1440
      ],
      "id": "277663ae-efa7-4ccb-b6e2-6d4063977381",
      "name": "ListMessages-Supabase2",
      "alwaysOutputData": true,
      "credentials": {
        "supabaseApi": {
          "id": "Iw5b75u1jCRMGGzJ",
          "name": "Ana Miranda"
        }
      }
    },
    {
      "parameters": {
        "aggregate": "aggregateAllItemData",
        "destinationFieldName": "conversas",
        "include": "specifiedFields",
        "fieldsToInclude": "id,message_type,created_at,user_message, bot_message,phone, conversation_id",
        "options": {}
      },
      "type": "n8n-nodes-base.aggregate",
      "typeVersion": 1,
      "position": [
        1120,
        1440
      ],
      "id": "cd7f25f6-2693-4ed2-bf9f-0f37d9018583",
      "name": "Aggregate2"
    },
    {
      "parameters": {
        "jsCode": "return $('Aggregate2').all().map(item => {\n  // Tenta acessar a propriedade 'conversas' e verifica se ela é um array\n  const conversas = item.json.conversas || []; // Garante que é pelo menos um array vazio\n\n  if (!Array.isArray(conversas)) {\n    throw new Error(\"A propriedade 'conversas' não é um array.\");\n  }\n\n  // Processa cada conversa e verifica as mensagens\n  const textoUnico = conversas.map(conversa => {\n    const cliente = conversa.user_message || \"sem mensagem do chatbot\";\n    const agente = conversa.bot_message || \"sem resposta\";\n    \n    // Verifica se a data existe e formata\n    const dataOriginal = conversa.created_at || \"Data da Mensagem indisponível\";\n    const dataFormatada = dataOriginal !== \"Data da Mensagem indisponível\"\n      ? formatarData(dataOriginal)\n      : dataOriginal;\n\n    return `em: ${dataFormatada}\\n\\n - agente(chatbot): ${agente} \\n - cliente: ${cliente}\\n`;\n  }).join('\\n\\n');\n\n  // Retorna o texto final como resultado\n  return {\n    json: {\n      texto: textoUnico\n    }\n  };\n});\n\n// Função para formatar a data\nfunction formatarData(dataString) {\n  const data = new Date(dataString); // Converte a string em objeto Date\n  if (isNaN(data)) {\n    return \"Data inválida\"; // Retorna se a data não for válida\n  }\n  \n  // Formata no padrão DD/MM/YYYY HH:mm\n  const dia = String(data.getDate()).padStart(2, '0');\n  const mes = String(data.getMonth() + 1).padStart(2, '0'); // Mês começa em 0\n  const ano = data.getFullYear();\n  const horas = String(data.getHours()).padStart(2, '0');\n  const minutos = String(data.getMinutes()).padStart(2, '0');\n  \n  return `${dia}/${mes}/${ano} ${horas}:${minutos}`;\n}\n"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        1360,
        1440
      ],
      "id": "d4a3968a-ee3a-4987-9a0e-83be1fcae89f",
      "name": "Code6"
    },
    {
      "parameters": {
        "options": {}
      },
      "type": "n8n-nodes-base.splitInBatches",
      "typeVersion": 3,
      "position": [
        580,
        1420
      ],
      "id": "559b4473-0364-4c0b-b23b-942fced84662",
      "name": "Loop Over Items6"
    },
    {
      "parameters": {
        "promptType": "define",
        "text": "=Você é um agente resumidor de casos dos clientes, você analisa a conversa entre o agente e o cliente e cria um resumo do problema que ele está enfrentando de forma bem detalhada somente do problema do cliente e preferencia pela data da sessão, não precisa de informações sobre as respostas do agente para o cliente, e gera o resultado sem dicas de uso ou informações extras \n\n# Conversa entre o cliete e o agente de IA:\n{{ $json.texto }}"
      },
      "type": "@n8n/n8n-nodes-langchain.chainLlm",
      "typeVersion": 1.5,
      "position": [
        1600,
        1420
      ],
      "id": "248a9b72-0dea-4dcb-aeac-b68b8b0fb712",
      "name": "Basic LLM Chain1"
    },
    {
      "parameters": {
        "content": "# Filtro de menagem",
        "height": 80,
        "width": 356,
        "color": 5
      },
      "id": "6fd0839a-ae6c-46f8-b9fe-6245ee22abef",
      "name": "Sticky Note7",
      "type": "n8n-nodes-base.stickyNote",
      "typeVersion": 1,
      "position": [
        -2840,
        1100
      ]
    },
    {
      "parameters": {
        "operation": "set",
        "key": "={{ $json.message.chat_id }}_block",
        "value": "true",
        "keyType": "string",
        "expire": true,
        "ttl": 4320000
      },
      "id": "24ffe03e-145a-4e11-aaf5-e6ecef7614e1",
      "name": "PARA IA",
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        -4340,
        1380
      ],
      "credentials": {
        "redis": {
          "id": "SWzSXUhno9OVr2z8",
          "name": "Redis account"
        }
      }
    },
    {
      "parameters": {
        "operation": "get",
        "propertyName": "block",
        "key": "={{ $json.message.chat_id }}_block",
        "options": {}
      },
      "id": "060818a9-a1bf-4c7f-acdd-fbed010eb030",
      "name": "Verificar Atendimento",
      "type": "n8n-nodes-base.redis",
      "typeVersion": 1,
      "position": [
        -4340,
        1540
      ],
      "credentials": {
        "redis": {
          "id": "SWzSXUhno9OVr2z8",
          "name": "Redis account"
        }
      }
    },
    {
      "parameters": {
        "jsCode": "const data = $item(0).$node[\"Compara Get Memory1\"].json[\"Redis2\"]; // Obtém o valor de Redis2 do nó \"If\"\n\n// Verifica se o dado é uma string que representa um array, e converte se necessário\nlet array = Array.isArray(data) ? data : JSON.parse(data);\n\n// Junta os elementos do array com um espaço entre eles\nconst mensagem_completa = array.join(\" \");\n\n// Retorna o resultado com o nome da variável \"mensagem_completa\"\nreturn [{ json: { mensagem_completa } }];\n"
      },
      "id": "db66dbaa-8c06-465c-90ca-eca266525d0f",
      "name": "Mensagem Completa",
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        -1040,
        1320
      ]
    }
  ],
  "pinData": {},
  "connections": {
    "OpenAI Chat Model1": {
      "ai_languageModel": [
        [
          {
            "node": "buscar_documentos",
            "type": "ai_languageModel",
            "index": 0
          }
        ]
      ]
    },
    "Default Data Loader": {
      "ai_document": [
        [
          {
            "node": "Insert into Supabase Vectorstore",
            "type": "ai_document",
            "index": 0
          }
        ]
      ]
    },
    "Embeddings OpenAI1": {
      "ai_embedding": [
        [
          {
            "node": "Insert into Supabase Vectorstore",
            "type": "ai_embedding",
            "index": 0
          }
        ]
      ]
    },
    "Download File": {
      "main": [
        [
          {
            "node": "Switch",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "File Created": {
      "main": [
        [
          {
            "node": "Set File ID",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "File Updated": {
      "main": [
        [
          {
            "node": "Set File ID",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Extract Document Text": {
      "main": [
        [
          {
            "node": "Insert into Supabase Vectorstore",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Embeddings OpenAI": {
      "ai_embedding": [
        [
          {
            "node": "Supabase Vector Store",
            "type": "ai_embedding",
            "index": 0
          }
        ]
      ]
    },
    "Set File ID": {
      "main": [
        [
          {
            "node": "Deleta linhas antigas do documento",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Extract PDF Text": {
      "main": [
        [
          {
            "node": "Insert into Supabase Vectorstore",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Aggregate": {
      "main": [
        [
          {
            "node": "Summarize",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Character Text Splitter": {
      "ai_textSplitter": [
        [
          {
            "node": "Default Data Loader",
            "type": "ai_textSplitter",
            "index": 0
          }
        ]
      ]
    },
    "Summarize": {
      "main": [
        [
          {
            "node": "Insert into Supabase Vectorstore",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Switch": {
      "main": [
        [
          {
            "node": "Extract PDF Text",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Extract from Excel",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Extract Document Text",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Insert into Supabase Vectorstore": {
      "main": [
        [
          {
            "node": "Loop Over Items",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Supabase Vector Store": {
      "ai_vectorStore": [
        [
          {
            "node": "buscar_documentos",
            "type": "ai_vectorStore",
            "index": 0
          }
        ]
      ]
    },
    "Extract from Excel": {
      "main": [
        [
          {
            "node": "Aggregate",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Supabase": {
      "main": [
        [
          {
            "node": "If1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "If1": {
      "main": [
        [
          {
            "node": "Get Dados",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Gerar sessionID",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Gerar sessionID": {
      "main": [
        [
          {
            "node": "Supabase1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Supabase1": {
      "main": [
        [
          {
            "node": "Code1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "OpenAI3": {
      "ai_languageModel": [
        [
          {
            "node": "Parser  Chain",
            "type": "ai_languageModel",
            "index": 0
          }
        ]
      ]
    },
    "Loop Over Items3": {
      "main": [
        [],
        [
          {
            "node": "Evolution API",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "OutputParser1": {
      "ai_outputParser": [
        [
          {
            "node": "Parser  Chain",
            "type": "ai_outputParser",
            "index": 0
          }
        ]
      ]
    },
    "Parser  Chain": {
      "main": [
        [
          {
            "node": "Split de Mensagem",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Switch3": {
      "main": [
        [
          {
            "node": "Menos que 240 Caracteres",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Menos que 240 Caracteres",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Menos que 240 Caracteres",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Menos que 240 Caracteres",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "OpenAI Chat Model": {
      "ai_languageModel": [
        [
          {
            "node": "Atendente",
            "type": "ai_languageModel",
            "index": 0
          }
        ]
      ]
    },
    "Code1": {
      "main": [
        [
          {
            "node": "Switch1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Atendente": {
      "main": [
        [
          {
            "node": "Switch2",
            "type": "main",
            "index": 0
          },
          {
            "node": "Busca Telefone",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Loop Over Items": {
      "main": [
        [],
        [
          {
            "node": "Download File",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Postgres Chat Memory": {
      "ai_memory": [
        [
          {
            "node": "Atendente",
            "type": "ai_memory",
            "index": 0
          }
        ]
      ]
    },
    "Webhook EVO": {
      "main": [
        [
          {
            "node": "dados_para_atendimento_humano1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Switch2": {
      "main": [
        [
          {
            "node": "Switch3",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Evolution API1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "OpenAI Chat Model2": {
      "ai_languageModel": [
        [
          {
            "node": "Basic LLM Chain1",
            "type": "ai_languageModel",
            "index": 0
          }
        ]
      ]
    },
    "Evolution API": {
      "main": [
        [
          {
            "node": "1 segundo",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Evolution API1": {
      "main": [
        [
          {
            "node": "ListChats-Supabase2",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Merge1": {
      "main": [
        [
          {
            "node": "Cria Histórico Supabase",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "If4": {
      "main": [
        [
          {
            "node": "Adiciona CHAT supabase",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Atualiza CHAT Supabase",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Date & Time1": {
      "main": [
        [
          {
            "node": "Supabase",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Busca Telefone": {
      "main": [
        [
          {
            "node": "If4",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Adiciona CHAT supabase": {
      "main": [
        [
          {
            "node": "Merge1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Atualiza CHAT Supabase": {
      "main": [
        [
          {
            "node": "Merge1",
            "type": "main",
            "index": 1
          }
        ]
      ]
    },
    "Cria Histórico Supabase": {
      "main": [
        [
          {
            "node": "Delete Memory",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Menos que 240 Caracteres": {
      "main": [
        [
          {
            "node": "Evolution API5",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Parser  Chain",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Split de Mensagem": {
      "main": [
        [
          {
            "node": "Loop Over Items3",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "1 segundo": {
      "main": [
        [
          {
            "node": "Loop Over Items3",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Retorna ID do arquivo": {
      "main": [
        [
          {
            "node": "Loop Over Items",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Deleta linhas antigas do documento": {
      "main": [
        [
          {
            "node": "Retorna ID do arquivo",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Convert to File1": {
      "main": [
        [
          {
            "node": "OpenAI1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Edit Fields3": {
      "main": [
        [
          {
            "node": "Convert to File1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "If3": {
      "main": [
        [
          {
            "node": "Redis5",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Redis4",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Redis4": {
      "main": [
        [
          {
            "node": "Get Memory 1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Redis5": {
      "main": [
        [
          {
            "node": "Get Memory 1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Switch1": {
      "main": [
        [
          {
            "node": "Text Memory1",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Text Memory1",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Audio Memory1",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Edit Fields3",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "OpenAI1": {
      "main": [
        [
          {
            "node": "If3",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Compara Get Memory1": {
      "main": [
        [
          {
            "node": "Mensagem Completa",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Text Memory1": {
      "main": [
        [
          {
            "node": "Get Memory 1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Audio Memory1": {
      "main": [
        [
          {
            "node": "Get Memory 1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Edit Fields8": {
      "main": [
        [
          {
            "node": "Compara Get Memory1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Wait3": {
      "main": [
        [
          {
            "node": "Get Memory 2",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Get Memory 1": {
      "main": [
        [
          {
            "node": "Wait3",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Get Memory 2": {
      "main": [
        [
          {
            "node": "Edit Fields8",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "buscar_documentos": {
      "ai_tool": [
        [
          {
            "node": "Atendente",
            "type": "ai_tool",
            "index": 0
          }
        ]
      ]
    },
    "Get Dados": {
      "main": [
        [
          {
            "node": "Code1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "dados_para_atendimento_humano1": {
      "main": [
        [
          {
            "node": "Switch6",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Switch Block": {
      "main": [
        [
          {
            "node": "Variáveis",
            "type": "main",
            "index": 0
          }
        ],
        []
      ]
    },
    "Switch6": {
      "main": [
        [
          {
            "node": "PARA IA",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Verificar Atendimento",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Variáveis": {
      "main": [
        [
          {
            "node": "Date & Time1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Evolution API5": {
      "main": [
        []
      ]
    },
    "ListChats-Supabase2": {
      "main": [
        [
          {
            "node": "Loop Over Items6",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "ListMessages-Supabase2": {
      "main": [
        [
          {
            "node": "Aggregate2",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Aggregate2": {
      "main": [
        [
          {
            "node": "Code6",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Loop Over Items6": {
      "main": [
        [],
        [
          {
            "node": "ListMessages-Supabase2",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Code6": {
      "main": [
        [
          {
            "node": "Basic LLM Chain1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Basic LLM Chain1": {
      "main": [
        [
          {
            "node": "Evolution API2",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Verificar Atendimento": {
      "main": [
        [
          {
            "node": "Switch Block",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Mensagem Completa": {
      "main": [
        [
          {
            "node": "Atendente",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  },
  "active": true,
  "settings": {
    "executionOrder": "v1",
    "callerPolicy": "workflowsFromSameOwner"
  },
  "versionId": "2ca492f9-2874-4644-93c3-9a59b694cb24",
  "meta": {
    "templateCredsSetupCompleted": true,
    "instanceId": "50ba3023c04b05fa788f0937438e6d2f11eae334d2ec41359f847de6221c08ed"
  },
  "id": "Jz9I2y0m2jBLvfnz",
  "tags": [
    {
      "createdAt": "2025-02-15T16:39:06.070Z",
      "updatedAt": "2025-02-15T16:39:06.070Z",
      "id": "WHsCxZK9iZ7fLl9g",
      "name": "Ana Miranda"
    }
  ]
}
