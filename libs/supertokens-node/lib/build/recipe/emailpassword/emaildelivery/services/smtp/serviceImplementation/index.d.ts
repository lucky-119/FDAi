// @ts-nocheck
import { TypeEmailPasswordEmailDeliveryInput } from "../../../../types";
import { Transporter } from "nodemailer";
import { ServiceInterface } from "../../../../../../ingredients/emaildelivery/services/smtp";
export declare function getServiceImplementation(
    transporter: Transporter,
    from: {
        name: string;
        email: string;
    }
): ServiceInterface<TypeEmailPasswordEmailDeliveryInput>;
