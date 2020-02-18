import template from './swag-migration-shop-information.html.twig';
import './swag-migration-shop-information.scss';

const { Component } = Shopware;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();
const { format } = Shopware.Utils;
const { Criteria } = Shopware.Data;

const BADGE_TYPE = Object.freeze({
    SUCCESS: 'success',
    DANGER: 'danger'
});

Component.register('swag-migration-shop-information', {
    template,

    inject: {
        /** @var {MigrationApiService} migrationService */
        migrationService: 'migrationService',
        repositoryFactory: 'repositoryFactory'
    },

    props: {
        connected: {
            type: Boolean,
            default: false
        }
    },

    data() {
        return {
            showMoreInformation: true,
            showConfirmModal: false,
            lastConnectionCheck: '-',
            lastMigrationDate: '-',
            connection: null,
            context: Shopware.Context.api
        };
    },

    filters: {
        localizedNumberFormat(value) {
            const locale = `${this.adminLocaleLanguage}-${this.adminLocaleRegion}`;
            const formatter = new Intl.NumberFormat(locale);
            return formatter.format(value);
        }
    },

    computed: {
        ...mapState('swagMigration/process', [
            'connectionId',
            'environmentInformation'
        ]),

        ...mapGetters([
            'adminLocaleLanguage',
            'adminLocaleRegion'
        ]),

        displayEnvironmentInformation() {
            return this.environmentInformation === null ? {} :
                this.environmentInformation;
        },

        migrationRunRepository() {
            return this.repositoryFactory.create('swag_migration_run');
        },

        migrationConnectionRepository() {
            return this.repositoryFactory.create('swag_migration_connection');
        },

        connectionName() {
            return this.connection === null ? '' :
                this.connection.name;
        },

        shopUrl() {
            return this.displayEnvironmentInformation.sourceSystemDomain === undefined ? '' :
                this.displayEnvironmentInformation.sourceSystemDomain.replace(/^\s*https?:\/\//, '');
        },

        shopUrlPrefix() {
            if (this.displayEnvironmentInformation.sourceSystemDomain === undefined) {
                return '';
            }

            const match = this.displayEnvironmentInformation.sourceSystemDomain.match(/^\s*https?:\/\//);
            if (match === null) {
                return '';
            }

            return match[0];
        },

        sslActive() {
            return (this.shopUrlPrefix === 'https://');
        },

        shopUrlPrefixClass() {
            return this.sslActive ? 'swag-migration-shop-information__shop-domain-prefix--is-ssl' : '';
        },

        connectionBadgeLabel() {
            if (this.serverUnreachable) {
                return 'swag-migration.index.shopInfoCard.serverUnreachable';
            }

            if (this.connected) {
                return 'swag-migration.index.shopInfoCard.connected';
            }

            return 'swag-migration.index.shopInfoCard.notConnected';
        },

        connectionBadgeVariant() {
            if (this.connected) {
                return BADGE_TYPE.SUCCESS;
            }

            return BADGE_TYPE.DANGER;
        },

        shopFirstLetter() {
            return this.displayEnvironmentInformation.sourceSystemName === undefined ? 'S' :
                this.displayEnvironmentInformation.sourceSystemName[0];
        },

        profile() {
            return this.connection === null || this.connection.profile === undefined ? '' :
                `${this.connection.profile.sourceSystemName} ${this.connection.profile.version} - ${this.connection.profile.author}`;
        },

        profileIcon() {
            return this.connection === null ||
                this.connection.profile === undefined ||
                this.connection.profile.icon === undefined ? null : this.connection.profile.icon;
        },

        gateway() {
            return this.connection === null || this.connection.gateway === undefined ? '' :
                this.connection.gateway.snippet;
        },

        lastConnectionCheckDateTimeParams() {
            return {
                date: this.getDateString(this.lastConnectionCheck),
                time: this.getTimeString(this.lastConnectionCheck)
            };
        },

        lastMigrationDateTimeParams() {
            return {
                date: this.getDateString(this.lastMigrationDate),
                time: this.getTimeString(this.lastMigrationDate)
            };
        }
    },

    watch: {
        connectionId: {
            immediate: true,
            /**
             * @param {string} newConnectionId
             */
            handler(newConnectionId) {
                this.fetchConnection(newConnectionId);
            }
        }
    },

    created() {
        this.updateLastMigrationDate();
    },

    methods: {
        updateLastMigrationDate() {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('status', 'finished'));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            this.migrationRunRepository.search(criteria, this.context).then((runs) => {
                if (runs.length > 0) {
                    this.lastMigrationDate = runs.first().createdAt;
                } else {
                    this.lastMigrationDate = '-';
                }
            });
        },

        /**
         * @param {string} connectionId
         */
        fetchConnection(connectionId) {
            this.migrationConnectionRepository.get(connectionId, this.context).then((connection) => {
                delete connection.credentialFields;
                this.connection = connection;
                this.lastConnectionCheck = new Date();

                this.migrationService.getProfileInformation(connection.profileName, connection.gatewayName).then((profileInformation) => {
                    this.connection.profile = profileInformation.profile;
                    this.connection.gateway = profileInformation.gateway;
                });
            });
        },

        getTimeString(date) {
            return format.date(date, {
                day: undefined,
                month: undefined,
                year: undefined,
                hour: 'numeric',
                minute: '2-digit'
            });
        },

        getDateString(date) {
            return format.date(date);
        },

        onClickEditConnectionCredentials() {
            this.$router.push({
                name: 'swag.migration.wizard.credentials',
                params: {
                    connectionId: this.connectionId
                }
            });
        },

        onClickCreateConnection() {
            this.$router.push({
                name: 'swag.migration.wizard.connectionCreate'
            });
        },

        onClickSelectConnection() {
            this.$router.push({
                name: 'swag.migration.wizard.connectionSelect'
            });
        },

        onClickProfileInstallation() {
            this.$router.push({
                name: 'swag.migration.wizard.profileInstallation'
            });
        },

        onClickRemoveConnectionCredentials() {
            this.migrationService.updateConnectionCredentials(
                this.connectionId,
                null
            ).then(() => {
                this.$router.go(); // Refresh the page
            });
        }
    }
});
